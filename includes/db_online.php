<?php
/**
 * Lógica de processamento de eventos para a página online.php
 */

// Busca encontros do BD, agrupa por dia
$meetings   = getMeetings();
$languages  = getLanguages();
$byDay      = [];
$byLanguage = [];

foreach ($meetings as $m) {
    $byDay[$m['day_of_week']][]          = $m;
    $byLanguage[$m['language_id']][]     = $m;
}

// Varivel usada em online.php para renderizar os dias
$dayNames = [];
foreach (range(1, 7) as $d) {
    $dayNames[$d] = getDayName($d);
}

$currentDayOfWeek = (int)date('N'); // 1=Seg ... 7=Dom
$currentHour      = (int)date('G');

// Slug já foi resolvido pelo router.php antes de incluir este arquivo.
// O router.php injeta: $_GET['view'], $_GET['idioma'], $_GET['dia'] conforme necessário.
// Aqui apenas lemos os valores prontos.


// Parâmetros iniciais da URL
$initialView = $_GET['view'] ?? 'day';

// 1. Inteligência para o Dia Padrão (Sempre carrega o dia atual)
$initialDay = $_GET['dia'] ?? $currentDayOfWeek;

// 2. Inteligência para o Idioma Padrão
$initialLang = $_GET['idioma'] ?? '';
if (empty($initialLang)) {
    foreach ($languages as $lang) {
        if (stripos($lang['name'], 'inglês') !== false) {
            $initialLang = $lang['id'];
            break;
        }
    }
    if (empty($initialLang) && !empty($languages)) {
        foreach ($languages as $lang) {
            if (!empty($byLanguage[$lang['id']])) {
                $initialLang = $lang['id'];
                break;
            }
        }
    }
}

// 3. Prepara dados para o botão de Dropdown inicial
$initialLangName  = 'Carregando...';
$initialFlagCode  = '';
$initialFlagEmoji = '';

foreach ($languages as $lang) {
    if ($lang['id'] == $initialLang) {
        $initialLangName  = $lang['name'];
        $initialFlagCode  = $lang['flag_code']  ?? '';
        $initialFlagEmoji = $lang['flag_emoji'] ?? '';
        break;
    }
}
?>
