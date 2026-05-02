<?php
/**
 * Lógica de processamento de eventos para a página online.php
 */

// Busca eventos do BD, agrupa por dia
$events     = getEvents();
$languages  = getLanguages();
$byDay      = [];
$byLanguage = [];

foreach ($events as $e) {
    $byDay[$e['day_of_week']][]          = $e;
    $byLanguage[$e['language_id']][]     = $e;
}

$currentDayOfWeek = (int)date('N'); // 1=Seg ... 7=Dom
$currentHour      = (int)date('G');

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
