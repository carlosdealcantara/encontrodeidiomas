<?php
/**
 * ================================================================
 * HELPER DE CÁLCULO DE MENSALIDADE E MULTA DE ATUALIZAÇÃO
 * Mentoria Encontro de Idiomas
 * ================================================================
 */

/**
 * Obtém o valor de tabela vigente a partir do pricing.php
 *
 * @param string $lang 'en' ou 'es'
 * @return float
 */
function obterValorTabelaVigente($lang = 'en') {
    $pricingPaths = [
        __DIR__ . '/../../mentoria/pricing.php',
        __DIR__ . '/../mentoria/pricing.php',
        dirname(__DIR__, 2) . '/mentoria/pricing.php',
        dirname(__DIR__) . '/mentoria/pricing.php',
    ];

    foreach ($pricingPaths as $pPath) {
        if (file_exists($pPath)) {
            require_once $pPath;
            if ($lang === 'es') {
                return isset($pricing_espanhol_mensal) ? (float)$pricing_espanhol_mensal : 175.00;
            } else {
                return isset($pricing_mensal) ? (float)$pricing_mensal : 275.00;
            }
        }
    }

    return ($lang === 'es') ? 175.00 : 275.00;
}

/**
 * Calcula as condições de reajuste e multa de atraso para o aluno.
 *
 * Regra:
 * - Vigência geral: a regra com 2 vidas e multa de regularização entra em vigor após 30/11/2026 23:59:59
 *   (período de Black Friday / pré-lançamento a R$ 250).
 * - Exceção / Modo de Teste: se $isTestMode for true, simula a vigência plena imediatamente para testar o fluxo.
 *
 * @param float $valorAluno
 * @param string $lang 'en' ou 'es'
 * @param int $diasAtraso Número de dias corridos desde o vencimento (>= 0)
 * @param bool $isTestMode Se true, ignora a trava de 30/Nov e força o cálculo das 2 vidas e multa
 * @return array
 */
function calcularCondicoesAtraso($valorAluno, $lang = 'en', $diasAtraso = 0, $isTestMode = false) {
    $valorAluno = (float)$valorAluno;
    $diasAtraso = max(0, (int)$diasAtraso);
    $valorTabela = obterValorTabelaVigente($lang);

    $fimPromocao = new DateTime('2026-11-30 23:59:59', new DateTimeZone('America/Sao_Paulo'));
    $agora = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
    $emPeriodoPromocional = ($agora <= $fimPromocao);

    // Se estiver em período de Black Friday e NÃO for modo de teste:
    // Mantém a oferta promocional sem reajuste de perda de desconto e sem multa
    if ($emPeriodoPromocional && !$isTestMode) {
        return [
            'valor_antigo'     => $valorAluno,
            'valor_tabela'     => $valorTabela,
            'gap'              => 0,
            'valor_reajustado' => $valorAluno,
            'multa_base'       => 0.00,
            'multa_total'      => 0.00,
            'dias_atraso'      => $diasAtraso,
            'em_promocao'      => true,
            'is_test_mode'     => false,
        ];
    }

    $gap = $valorTabela - $valorAluno;

    if ($gap > 0.5) {
        // Aluno em valor promocional / histórico (abaixo da tabela)
        $valorReajustado = $valorAluno + ($gap * 0.5);
        $multaBase = $gap;
        $multaTotal = $multaBase + ($diasAtraso * 1.00);
    } else {
        // Aluno já na tabela vigente
        $valorReajustado = $valorTabela;
        $multaBase = 5.00;
        $multaTotal = $multaBase + ($diasAtraso * 0.50);
    }

    return [
        'valor_antigo'     => $valorAluno,
        'valor_tabela'     => $valorTabela,
        'gap'              => max(0, $gap),
        'valor_reajustado' => round($valorReajustado, 2),
        'multa_base'       => round($multaBase, 2),
        'multa_total'      => round($multaTotal, 2),
        'dias_atraso'      => $diasAtraso,
        'em_promocao'      => false,
        'is_test_mode'     => $isTestMode,
    ];
}

/**
 * Formata um valor numérico para exibição em Real (ex: R$ 250 ou R$ 262,50)
 *
 * @param float $valor
 * @return string
 */
function formatarMoedaMentoria($valor) {
    if (fmod($valor, 1) == 0) {
        return 'R$ ' . number_format($valor, 0, ',', '.');
    }
    return 'R$ ' . number_format($valor, 2, ',', '.');
}
