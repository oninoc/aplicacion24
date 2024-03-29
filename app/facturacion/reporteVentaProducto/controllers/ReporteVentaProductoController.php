<?php
/* 
* ---------------------------------------
* --------- CREATED BY LV ----------
* Autor:        Super 
* Fecha:        10-09-2018 17:09:53 
* Descripcion : ReporteVentaProductoController.php
* ---------------------------------------
*/ 

namespace Facturacion\ReporteVentaProducto\Controllers;
   
use \Vendor\Controller;
use \Facturacion\ReporteVentaProducto\Filters\ReporteVentaProductoFilter;
use \Dompdf\Dompdf;

class ReporteVentaProductoController extends \Facturacion\ReporteVentaProducto\Models\ReporteVentaProductoModel {
    
    use Controller {
        Controller::__construct as private __cConstruct;
    }
    use ReporteVentaProductoFilter {
        ReporteVentaProductoFilter::__construct as private __fConstruct;
    }
    
    public function __construct() {
        parent::__construct();  /* constructor del NotaVentaModel */
        $this->__cConstruct();  /* constructor del Controller */
        $this->__fConstruct();  /* constructor del NotaVentaFilter */
    }
    
    public function index() {

    }
    
    public function grid() {
        echo json_encode($this->spGrid());
    }
    
    public function getPDF() {
        echo json_encode($this->_pdfDocumento($this->_form->_key, true));
    }
    
    private function _pdfDocumento($id, $return = false) {
        $company = $this->qCompany();
        $head = $this->qHeadDocumento($id);
        $detail = $this->qDetailDocumento($id);

        /* RUC | TIPO DE DOCUMENTO | SERIE | NUMERO | MTO TOTAL IGV | MTO TOTAL DEL COMPROBANTE | FECHA DE EMISION |TIPO DE DOCUMENTO ADQUIRENTE | NUMERO DE DOCUMENTO ADQUIRENTE | */
        $text_qr = $company['ruc'] . '|' . $head['tipo_comprobante'] . '|' . $head['serie'] . '|' . $head['numero_documento'] . '|' . $head['total_igv'] . '|' . $head['total_venta'] . '|' . $head['fecha_emision'] . '|' . $head['id_tipo_documento_identidad'] . '|' . $head['documento_identidad'] . '|';
        $ruta_qr = BASE_URL . 'files/temp/QR_' . $id . '.png';
        $qr = ROOT . 'files' . DS . 'temp' . DS . 'QR_' . $id . '.png';
        $ruta_qr = $qr;
        require_once ROOT . 'libs' . DS . 'phpqrcode' . DS . 'qrlib.php';

        \QR\QRcode::png($text_qr, $qr, 'Q', 15, 0);

        $DomPDF = new DOMPDF();
        $DomPDF->set_option('enable_remote', TRUE);
        $file = ROOT . "files" . DS . "temp" . DS . "tmpVenta".$head['id_venta'].".pdf";
        Obj()->Vendor->Tools->deleteFile($file);

        $productos = '';
        foreach ($detail as $value) {
            $productos .= '
            <tr class="detalletable"> 
              <td>' . $value['catalogo'] . '</td>
              <td style="text-align:right">' . $value['cantidad'] . '</td>
              <td style="text-align:right">' . $value['precio_unitario'] . '</td>
              <td style="text-align:right">' . $value['sub_total'] . '</td>
              <td class="ulttable" style="text-align:right">' . $value['total'] . '</td>
            </tr>';
        }
        $direccionCliente = '';
        if (!empty($head['direccion_fiscal'])) {
            $direccionCliente = '<tr>
                                    <td class="tdatoslabel">
                                            <div style="padding-bottom: 10px; padding-right: 19px;">
                                                    <span style="float: left;">Dirección:</span>
                                                    <div style="margin-left: 105px; border-bottom: solid 1px #000;"> ' . $head['direccion_fiscal'] . '</div>
                                            </div>
                                    </td>
                                </tr>';
        }
        $hash = '';
        if (!empty($head['hash_cpe'])) {
            $hash = '<br><span class="codigofac">HASH: ' . $head['hash_cpe'] . '</span>';
        }

        $html = file_get_contents(BASE_URL . 'files/template/comprobante/boletaFactura.html');

        $html = str_replace("{BASE_URL}", BASE_URL, $html);
        $html = str_replace("{LOGO}", Obj()->Vendor->Session->get('app_logo'), $html);
        $html = str_replace("{R_SOCIAL_E}", $company['razon_social'], $html);
        $html = str_replace("{R_DIRECCION_E}", $company['direccion'], $html);
        $html = str_replace("{R_TELEFONO_E}", $company['telefono'], $html);
        $html = str_replace("{R_EMAIL_E}", $company['email'], $html);
        $html = str_replace("{R_RUC_E}", $company['ruc'], $html);
        $html = str_replace("{NAME_DOC}", $head['name_tipo_comprobante'] . ' ELECTRONICA', $html);
        $html = str_replace("{NUM_DOC}", $head['serie'] . '-' . $head['numero_documento'], $html);
        $html = str_replace("{R_SOCIAL_C}", $head['razon_social'], $html);
        $html = str_replace("{FECHA_EMISION}", $head['fecha_emision'], $html);
        $html = str_replace("{TIPO_DOC_C}", $head['tipo_doc_identidad'], $html);
        $html = str_replace("{NUM_DOC_C}", $head['documento_identidad'], $html);
        $html = str_replace("{MONEDA}", $head['tipo_moneda_name'], $html);
        $html = str_replace("{R_DIRECCION_C}", $direccionCliente, $html);
        $html = str_replace("{DETAIL}", $productos, $html);
        $html = str_replace("{MONTO_LETRAS}", Obj()->Libs->NumberToString->convert($head['total_venta'], $head['tipo_moneda_name']), $html);
        $html = str_replace("{SUB_TOTAL}", $head['total_gravada'], $html);
        $html = str_replace("{TOTAL_IGV}", $head['total_igv'], $html);
        $html = str_replace("{TOTAL}", $head['total_venta'], $html);
        $html = str_replace("{LINK}", 'https://bit.ly/2HiRWZI', $html);
        $html = str_replace("{HASH}", $hash, $html);
        $html = str_replace("{QR}", $ruta_qr, $html);
        $html = str_replace("{TIPODOC}", ucfirst(strtolower($head['name_tipo_comprobante'])), $html);
        $html = str_replace("{CAJA}", $head['caja'], $html);
        $html = str_replace("{USER}", $head['vendedor'], $html);
        $html = str_replace("{OBS}", $head['observaciones'], $html);

        $DomPDF->load_html($html);


        $DomPDF->render();
        $pdf = $DomPDF->output();
        file_put_contents($file, $pdf);

        if (is_readable($file)) {
            if ($return) {
                return ['result' => 1, 'file' => BASE_URL . "files/temp/tmpVenta".$head['id_venta'].".pdf"];
            } else {
                echo json_encode(['result' => 1, 'file' => BASE_URL . "files/temp/tmpVenta".$head['id_venta'].".pdf"]);
            }
        } else {
            if ($return) {
                return ['result' => 2];
            } else {
                echo json_encode(['result' => 2]);
            }
        }
    }
}