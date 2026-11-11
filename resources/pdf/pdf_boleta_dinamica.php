<?php
/* =====================================================================
   BOLETAS DE LAS DINÁMICAS
   pdf_boleta_dinamica.php?id=<ID ganador>[&desde=N&hasta=M]
   - Sólo LEE T_RIFA_DINAMICAS_GANADORES (la redención se registra en
     models/Rifas.php op EV_REDIMIR), por eso reimprimir no altera nada.
   - Imprime una página (78x78 mm) por boleta redimida.
   ===================================================================== */
include("../../models/funciones.php");
require_once("../../models/RifasConfig.php");
require("../../resources/fpdf/fpdf.php");

class PDF extends FPDF
{
	function Footer()
	{
		$this->SetY(-15);
		$this->SetX(10);
		$this->SetFont('Arial', 'B', 7);
		$this->MultiCell(55, 8, 'Fecha/Hora :' . date('Y-m-d g:i:s'), 0, "C");
	}

	function Header()
	{
		$this->SetFont("Arial", "B", 10);
		$this->Ln();
		$this->line(0, 17, 140, 17);
		$this->SetX("0");
		$this->text(7, 15, "DISTRIBUIDORA FARMACEUTICA ROMA");
		$this->Ln();
		$this->line(0, 23, 140, 23);
	}

	function Texto($y, $txt, $size, $style, $h = 5)
	{
		$this->SetXY(0, $y);
		$this->SetFont("Arial", $style, $size);
		$this->MultiCell(78, $h, utf8_decode($txt), 0, "C");
	}

	function cuerpo()
	{
		conectar();
		$this->SetAutoPageBreak(false);
		$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

		$Query = GenerarArray("SELECT
								  G.ID,
								  G.DINAMICA,
								  G.PASSPORT,
								  G.BOLETAS,
								  G.BOLETAS_REDIMIDAS,
								  NOMBRES = ISNULL(T.NOMBRES, G.NOMBRES),
								  RAZON   = ISNULL(T.RAZON_COMERCIAL, ''),
								  FECHA   = CONVERT(varchar(16), G.FECHA_HORA, 120)
							   FROM T_RIFA_DINAMICAS_GANADORES G
							   LEFT JOIN T_TERCEROS T ON T.CODIGO_SAP = G.PASSPORT
							   WHERE G.ID = " . $id, "");

		if (count($Query) == 0 || (int)$Query[0]['BOLETAS_REDIMIDAS'] <= 0) {
			$this->AddPage();
			$this->Texto(35, count($Query) == 0 ? 'GANADOR NO ENCONTRADO' : 'SIN BOLETAS REDIMIDAS', 11, "B");
			return;
		}

		$r   = $Query[0];
		$din = evDinamicas();
		$premio = isset($din[$r['DINAMICA']]) ? $din[$r['DINAMICA']]['premio'] : $r['DINAMICA'];

		// Rango de boletas a imprimir (sólo las redimidas)
		$red   = (int)$r['BOLETAS_REDIMIDAS'];
		$desde = isset($_GET["desde"]) ? max(1, (int)$_GET["desde"]) : 1;
		$hasta = isset($_GET["hasta"]) ? min($red, (int)$_GET["hasta"]) : $red;
		if ($hasta < $desde) { $desde = 1; $hasta = $red; }

		for ($i = $desde; $i <= $hasta; $i++) {
			$this->AddPage();
			$this->Texto(25, $premio, 10, "B");
			$this->Texto(36, $r['NOMBRES'], 9, "");
			$this->Texto(41, $r['RAZON'], 9, "");
			$this->Texto(47, $r['PASSPORT'], 13, "B", 8);
			$this->Texto(55, 'Boleta ' . $i . ' de ' . $r['BOLETAS'], 9, "B");
			$this->Texto(60, sprintf('No. %s-%06d-%02d', $r['DINAMICA'], $r['ID'], $i), 7, "");
		}
	}
}

$pdf = new PDF('P', 'mm', array(78, 78));
$pdf->cuerpo();
$pdf->Output("");
