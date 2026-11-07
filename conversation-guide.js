'use strict';

// Each optional topic follows the same pattern: explore, clarify, then agree.
const CONVERSATION_GUIDE = [
 {id:'opening', icon:'👋', title:'Otevření a důvěra', field:'opening',
  questions:[
   'Jak se vám od našeho posledního setkání daří?',
   'Co by vám dnes pomohlo, aby pro vás tenhle rozhovor měl smysl?',
   'Co chcete stihnout v čase, který spolu máme?'
  ]},
 {id:'discovery', icon:'🔎', title:'Zjištění a zpřesnění potřeb', field:'findings',
  questions:[
   'Jak to máte dnes a co byste chtěl změnit?',
   'Kdybyste si mohl vybrat jednu věc, kterou vyřešíme nejdřív, která to bude a proč?',
   'Co už jste zkoušel a co vám v tom pomáhalo nebo bránilo?',
   'Je správně, že hlavní priorita je [priorita] a chcete ji řešit do [termín]?'
  ]},
 {id:'care', icon:'💚', title:'Péče a její frekvence', next:'care',
  questions:[
   'Jaká podpora by vám po dnešku byla nejvíc užitečná?',
   'Co byste chtěl při pravidelných setkáních procházet?',
   'Jak často a jakým způsobem vám dává smysl, abych se ozval?',
   'Podle čeho poznáme, že je potřeba se ozvat dřív?',
   'Který termín vám vyhovuje pro první kontrolu?'
  ]},
 {id:'crosssell', icon:'🌱', title:'Křížový prodej a další nabídka', next:'crosssell',
  questions:[
   'Co dalšího v téhle oblasti řešíte nebo zatím odkládáte?',
   'Jak to dnes řešíte a kde vám současné řešení přestává vyhovovat?',
   'Co je pro vás při srovnání možností nejdůležitější?',
   'Jaké podklady by vám pomohly rozhodnout, jestli má smysl pokračovat?',
   'Co z toho má smysl probrat příště a kdy?'
  ]},
 {id:'referral', icon:'🎁', title:'Doporučení a představení', next:'referral',
  questions:[
   'Co vám na našem rozhovoru nebo spolupráci přineslo největší užitek?',
   'Komu z vašeho okolí by podle vás podobný rozhovor mohl pomoct?',
   'Komu by dávalo smysl o tom říct a v jaké situaci právě je?',
   'Jak byste mu o tom řekl vlastními slovy?',
   'Jak vám vyhovuje udělat první krok? Co říkáte na to, že mu o tom povíte a dáte mi pak vědět?',
   'Pokud o to bude stát, jak vám vyhovuje, abyste nás propojil? Chcete domluvit i společnou krátkou schůzku?',
   'Jaký z toho máte pocit? Co by pro vás bylo přirozené a pohodlné?'
  ],
  hint:'Dohodni se, kdo koho osloví. Přímé oslovení nebo společnou schůzku domlouvej až tehdy, když s tím doporučený člověk souhlasí.'},
 {id:'cooperation', icon:'🤝', title:'Potenciál ke spolupráci', next:'cooperation',
  questions:[
   'Co vás na téhle práci nebo způsobu spolupráce zaujalo?',
   'Kde vidíte prostor, ve kterém bychom si mohli navzájem pomoct?',
   'Jaké zkušenosti nebo kontakty byste chtěl do spolupráce přinést?',
   'Jak by podle vás vypadala spolupráce, která dává smysl oběma?',
   'Co si potřebujeme vyjasnit, než zkusíme první společný krok?',
   'Co malého můžeme zkusit a kdy si řekneme, jak to dopadlo?'
  ]},
 {id:'recruitment', icon:'🧭', title:'Budování týmu a nábor', next:'recruitment',
  questions:[
   'Co vás láká na práci v týmu a co byste si chtěl ověřit?',
   'V jakém prostředí se vám pracuje nejlépe a jakou roli byste chtěl zkusit?',
   'Co se chcete naučit a jakou podporu byste na začátku ocenil?',
   'Kolik prostoru tomu můžete reálně věnovat?',
   'Jaký první krok vám dává smysl, abyste si udělal vlastní představu?'
  ]},
 {id:'team', icon:'👥', title:'Práce s týmem', next:'team_checkin',
  questions:[
   'Co se vám od minula nejvíc povedlo a co k tomu pomohlo?',
   'Na čem chcete pracovat jako na nejbližší prioritě?',
   'Co vás teď brzdí a jak vám můžu pomoct?',
   'Co si chcete samostatně převzít a co máme udělat společně?',
   'Kterou obchodní dovednost si chcete příště vyzkoušet nanečisto?',
   'Podle čeho poznáme, že je úkol hotový, a kdy si dáme krátkou zpětnou vazbu?'
  ]},
 {id:'closing', icon:'🏁', title:'Ověření, závěr a další krok', field:'summary',
  questions:[
   'Jak byste vlastními slovy shrnul, na čem jsme se shodli?',
   'Co jsem podle vás pochopil správně a co byste doplnil?',
   'Jaké otázky vám ještě zbývají?',
   'Jaký další krok teď dává smysl a kdo ho udělá?',
   'Kdy si výsledek společně potvrdíme?'
  ]}
];

const COLLABORATION_STAGES = {
 potential:'💡 Potenciál',
 discussing:'💬 Jednáme',
 onboarding:'🧭 Zaškolení',
 partner:'🤝 Partner',
 team:'👥 Člen týmu',
 no_interest:'⏸ Bez zájmu'
};
const TEAM_FOLLOWUPS = ['cooperation','recruitment','team_onboarding','team_coaching','team_checkin','team_task'];
const REFERRAL_INTROS = {
 client:'Klient se nejdřív ozve sám',
 introduction:'Klient nás propojí',
 joint_meeting:'Domluvíme společnou schůzku po souhlasu',
 direct:'Ozvu se přímo po potvrzení souhlasu',
 none:'Zatím bez dohody'
};
