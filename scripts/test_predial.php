<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/includes/predial.php";

$sample = <<<HTML
<table class="table table-bordered">
  <tr>
    <td colspan="3"><div>Total a pagar</div><small>(incluye redondeo)</small></td>
    <td colspan="2">$1,621.00</td>
  </tr>
</table>
HTML;

$conAdeudo = mxli_predial_parse_html($sample, "ST312002");
$okDebt = !empty($conAdeudo["tiene_adeudo"]) && (float)$conAdeudo["total_a_pagar"] === 1621.0;

$sampleOk = <<<HTML
<table>
  <tr><td>Total a pagar</td><td>$0.00</td></tr>
</table>
HTML;
$sinAdeudo = mxli_predial_parse_html($sampleOk, "ST312002");
$okClean = empty($sinAdeudo["tiene_adeudo"]) && ($sinAdeudo["estatus_consulta"] ?? "") === "sin_adeudo";

$empty = mxli_predial_parse_html("<div>sin tabla</div>", "ST312002");
$okMissing = ($empty["estatus_consulta"] ?? "") === "no_encontrado";

$sampleAseo = <<<'HTML'
<div class="alert alert-warning">
  Esta clave tiene multa(s): CONTRIBUYENTE CON ADEUDO EN ASEO PUBLICO $2,074.80
</div>
HTML;
$aseo = mxli_predial_parse_html($sampleAseo, "ST311019");
$okAseo = ($aseo["estatus_consulta"] ?? "") === "adeudo_aseo" && (float)($aseo["total_aseo"] ?? 0) === 2074.8;

echo $okDebt && $okClean && $okMissing && $okAseo ? "predial parser ok\n" : "predial parser FAIL\n";
if (!$okAseo) {
    echo json_encode($aseo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
}
if (!$okDebt) {
    echo json_encode($conAdeudo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
}

$live = $argv[1] ?? "";
if ($live !== "") {
    $r = mxli_predial_consultar($live, false);
    echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
}
