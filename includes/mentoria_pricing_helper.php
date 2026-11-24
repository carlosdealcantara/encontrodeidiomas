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
 * - Se o aluno paga menos que o valor de tabela (tem benefício/gap):
 *     1) Reajuste (Opção Contratual): perde 50% da diferença (1ª vida):
 *        novo_valor = valor_aluno + (gap * 0.5)
 *     2) Multa de Manutenção (Opção Alternativa):
 *        multa_base = gap (a diferença total da mensalidade)
 *        multa_total = multa_base + (dias_atraso * 1.00)
 * - Se o aluno já paga o valor de tabela (sem gap promocional):
 *     1) Reajuste: valor_tabela atualizado com reajuste de 0.5% a.m.
 *     2) Multa: R$ 5.00 base + (dias_atraso * 0.50)
 *
 * @param float $valorAluno
 * @param string $lang 'en' ou 'es'
 * @param int $diasAtraso Número de dias corridos desde o vencimento (>= 0)
 * @return array
 */
function calcularCondicoesAtraso($valorAluno, $lang = 'en', $diasAtraso = 0) {
    $valorAluno = (float)$valorAluno;
    $diasAtraso = max(0, (int)$diasAtraso);
    $valorTabela = obterValorTabelaVigente($lang);

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
