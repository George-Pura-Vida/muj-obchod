<?php
// ULOŽ MIMO VEŘEJNÝ WEB jako ../moje-schuzka-data/ai-secrets.php
// Nikdy nevkládej skutečný API klíč do GitHubu ani do tohoto ZIP souboru.
return [
    'openai_api_key' => '', // sem vlož klíč pouze na serveru, případně použij OPENAI_API_KEY
    'analysis_model' => 'gpt-6.1-terra',
    'monthly_transcriptions' => 20,
    'monthly_analyses' => 40,
    'daily_transcriptions' => 5,
    'daily_analyses' => 10,
    'max_audio_bytes' => 25 * 1024 * 1024,
];
