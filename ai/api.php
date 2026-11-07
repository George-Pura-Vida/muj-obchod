<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';

try {
    [$db, $cfg, $private] = ai_bootstrap();
    $uid = ai_user_id();
    $action = (string)($_GET['do'] ?? 'list');
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
        $sql = 'SELECT a.id,a.contact_id,a.kind,a.happened_at,a.notes,a.transcript,a.review,
                c.first_name,c.last_name,
                (SELECT r.id FROM recordings r WHERE r.activity_id=a.id AND r.user_id=a.user_id ORDER BY r.id DESC LIMIT 1) AS recording_id
                FROM activities a JOIN contacts c ON c.id=a.contact_id AND c.user_id=a.user_id
                WHERE a.user_id=? ORDER BY a.id DESC LIMIT 100';
        $q = $db->prepare($sql);
        $q->execute([$uid]);
        $items = $q->fetchAll();
        $since = gmdate('Y-m-01 00:00:00');
        $q = $db->prepare('SELECT operation,COUNT(*) AS cnt,COALESCE(SUM(estimated_usd),0) AS cost FROM ai_jobs WHERE user_id=? AND created_at>=? GROUP BY operation');
        $q->execute([$uid, $since]);
        $usage = ['transcribe' => ['count' => 0, 'estimated_usd' => 0], 'analyze' => ['count' => 0, 'estimated_usd' => 0]];
        foreach ($q->fetchAll() as $row) {
            $usage[$row['operation']] = ['count' => (int)$row['cnt'], 'estimated_usd' => (float)$row['cost']];
        }
        ai_ok(['items' => $items, 'csrf' => $_SESSION['csrf'], 'ai_available' => $cfg['openai_api_key'] !== '',
            'quotas' => ['transcriptions' => $cfg['monthly_transcriptions'], 'analyses' => $cfg['monthly_analyses']],
            'usage' => $usage]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') ai_error('Neznámá akce.', 404);
    ai_check_csrf();
    $data = ai_body();
    $id = (int)($data['activity_id'] ?? 0);
    if ($id <= 0) ai_error('Neplatná schůzka.');
    $q = $db->prepare('SELECT id,transcript FROM activities WHERE id=? AND user_id=?');
    $q->execute([$id, $uid]);
    $activity = $q->fetch();
    if (!$activity) ai_error('Schůzka nenalezena.', 404);

    if ($action === 'save') {
        $transcript = (string)($data['transcript'] ?? '');
        if (strlen($transcript) > 120000) ai_error('Přepis je příliš dlouhý.');
        $q = $db->prepare('UPDATE activities SET transcript=? WHERE id=? AND user_id=?');
        $q->execute([$transcript, $id, $uid]);
        ai_ok(['message' => 'Přepis uložen.']);
    }

    if (!in_array($action, ['transcribe', 'analyze'], true)) ai_error('Neznámá akce.', 404);
    @set_time_limit(180); // neobchází případný striktní timeout hostingu
    if ($cfg['openai_api_key'] === '') ai_error('OpenAI API ještě není aktivní. Nastav klíč na serveru.', 503);
    if (($data['consent_confirmed'] ?? false) !== true) ai_error('Před AI zpracováním potvrď oprávnění, informování účastníků a právní titul.', 403);
    $job = null;
    try {
        if ($action === 'transcribe') {
            $rid = (int)($data['recording_id'] ?? 0);
            $q = $db->prepare('SELECT filename,mime,bytes FROM recordings WHERE id=? AND activity_id=? AND user_id=?');
            $q->execute([$rid, $id, $uid]);
            $record = $q->fetch();
            if (!$record) ai_error('Nahrávka k tomuto zápisu nenalezena.', 404);
            $ext = strtolower(pathinfo((string)$record['filename'], PATHINFO_EXTENSION));
            $allowed = ['mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'mp4' => 'audio/mp4',
                'webm' => 'audio/webm', 'wav' => 'audio/wav'];
            if (!array_key_exists($ext, $allowed)) ai_error('Pro AI přepis použij MP3, M4A, MP4, WebM nebo WAV.', 415);
            $file = realpath($private . '/recordings/' . basename((string)$record['filename']));
            $root = realpath($private . '/recordings');
            if (!$file || !$root || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) ai_error('Nahrávka na serveru chybí.', 404);
            $bytes = filesize($file);
            if (!$bytes || $bytes > $cfg['max_audio_bytes']) ai_error('Pro přepis nahraj nebo komprimuj zvuk do 25 MB.', 413);
            $job = ai_claim($db, $uid, $id, 'transcribe', $cfg);
            $result = ai_call_transcription($file, $allowed[$ext], $cfg['openai_api_key']);
            $transcript = trim((string)$result['text']);
            if ($transcript === '') throw new RuntimeException('EMPTY_TRANSCRIPT');
            if (strlen($transcript) > 120000) throw new RuntimeException('TRANSCRIPT_TOO_LONG');
            $q = $db->prepare('UPDATE activities SET transcript=? WHERE id=? AND user_id=?');
            $q->execute([$transcript, $id, $uid]);
            // Přepis se účtuje za zvukové minuty. Délka není v tomto API výsledku
            // spolehlivě k dispozici; skutečný náklad sleduj v OpenAI Usage.
            ai_finish($db, $job, 'success');
            ai_ok(['transcript' => $transcript, 'message' => 'Přepis byl uložen do schůzky.']);
        }
        $transcript = trim((string)$activity['transcript']);
        if (strlen($transcript) < 30) ai_error('Pro analýzu je potřeba přepis alespoň 30 znaků.');
        if (strlen($transcript) > 60000) ai_error('Přepis je příliš dlouhý. Rozděl schůzku na kratší úseky.');
        $job = ai_claim($db, $uid, $id, 'analyze', $cfg);
        [$review, $usage] = ai_call_review($transcript, $cfg);
        $reviewText = ai_review_text($review);
        if (strlen($reviewText) > 20000) throw new RuntimeException('REVIEW_TOO_LONG');
        $q = $db->prepare('UPDATE activities SET review=? WHERE id=? AND user_id=?');
        $q->execute([$reviewText, $id, $uid]);
        $in = (int)($usage['input_tokens'] ?? 0);
        $out = (int)($usage['output_tokens'] ?? 0);
        // Výchozí model gpt-6.1-terra: $1 / 1M vstupních, $5 / 1M výstupních tokenů.
        $estimate = $cfg['model'] === AI_ANALYSIS_MODEL ? ($in * 1.0 + $out * 5.0) / 1000000 : null;
        ai_finish($db, $job, 'success', $in, $out, $estimate);
        ai_ok(['review' => $reviewText, 'structured' => $review, 'estimated_analysis_usd' => $estimate]);
    } catch (RuntimeException $e) {
        if ($job !== null) ai_finish($db, $job, 'failed', null, null, null, substr($e->getMessage(), 0, 60));
        $code = $e->getMessage();
        if ($code === 'OPENAI_HTTP_429') ai_error('AI služba je dočasně vytížená nebo má vyčerpaný limit.', 429);
        if ($code === 'OPENAI_HTTP_401' || $code === 'OPENAI_HTTP_403') ai_error('OpenAI API klíč nebo oprávnění projektu není v pořádku.', 503);
        ai_error('AI operaci se nepodařilo dokončit. Zkontroluj formát nahrávky, limity a stav API.', 502);
    }
} catch (PDOException $e) {
    error_log('AI SQLite error: ' . $e->getCode());
    ai_error('Databázový problém. Zkontroluj práva privátní složky.', 503);
} catch (Throwable $e) {
    error_log('AI internal error: ' . get_class($e));
    ai_error('Chyba serveru AI modulu.', 500);
}
