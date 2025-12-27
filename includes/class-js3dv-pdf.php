<?php
namespace JS\JS3DV;

class PDF_Generator {
    use Traits\Image_Handler;

    public function product_overview($name, $top_b64, $front_b64) {
        require_once JS3DV_PATH . 'includes/fpdf/fpdf.php';
        $top = $this->save_base64($top_b64, 'top');
        $front = $this->save_base64($front_b64, 'front');

        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial','B',16);
        $pdf->Cell(0,10,$name,0,1,'C');
        $pdf->Ln(10);
        $pdf->Cell(0,10,'Bovenaanzicht',0,1);
        $pdf->Image($top,10,$pdf->GetY(),190);
        $pdf->AddPage();
        $pdf->Cell(0,10,'Vooraanzicht',0,1);
        $pdf->Image($front,10,$pdf->GetY(),190);

        $file = wp_tempnam("product_{$name}") . '.pdf';
        $pdf->Output('F', $file);
        @unlink($top); @unlink($front);
        return $file;
    }

    public function invoice($order) {
        require_once plugin_dir_path(__FILE__) . 'includes/fpdf/fpdf.php';
    
        $pdf = new \FPDF();
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(true, 25);
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        
        // === Layout constants ===
        $logo_path = plugin_dir_path(__FILE__) . 'dev-data/logo_symbol.png';
        $logo_width = 40;
        $logo_y = 0; // Top padding
        $page_width = $pdf->GetPageWidth();
        
        // === Place logo ===
        $logo_x = ($page_width - $logo_width) / 2;
        $pdf->Image($logo_path, $logo_x, $logo_y, $logo_width);
        
        // Assume logo height is same as width (square)
        $logo_height = $logo_width;
        
        // === Contact & Business Info Blocks ===
        $block_width = ($page_width - $logo_width) / 2;
        $block_line_height = 5;
        $contact_lines = 4;
        $business_lines = 3;
        
        $contact_height = $contact_lines * $block_line_height;
        $business_height = $business_lines * $block_line_height;
        
        // Center vertically next to logo
        $contact_y = 5;
        $business_y = 5;
        
        $contact_x = 0;
        $business_x = $logo_x + $logo_width;
        
        // === Contact (Left of logo) ===
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetXY($contact_x, $contact_y);
        $pdf->MultiCell($block_width, $block_line_height, utf8_decode(
            "Adres: Molendijk-Zuid 21-02\n" .
            "5482 WZ Schijndel\n" .
            "Telefoon: 073-234 03 03\n" .
            "info@transporthoes.shop"
        ), 0, 'C');
        
        // === Business Info (Right of logo) ===
        $pdf->SetXY($business_x, $business_y);
        $pdf->MultiCell($block_width, $block_line_height, utf8_decode(
            "KVK: 93975740\n" .
            "BTW: NL8665.91.576.B.01\n\n" .
            "IBAN: NL95 ABNA 0895 2053 51"
        ), 0, 'C');
        
        // === Move Y below the entire banner area ===
        $banner_bottom_y = max($contact_y + $contact_height, $business_y + $business_height, $logo_y + $logo_height);
        $spacing_after_banner = 5;
        $pdf->SetY($banner_bottom_y + $spacing_after_banner);
        
        // === Full-width company title image ===
        $header_image_path = plugin_dir_path(__FILE__) . 'dev-data/company_title.png';
        $header_image_height = 20;
        $pdf->Image($header_image_path, 0, $pdf->GetY(), $page_width, $header_image_height);
        
        // === Move below title image for invoice body ===
        $pdf->SetY($pdf->GetY() + $header_image_height + 5);
        
        // === Invoice heading ===
        $pdf->Ln(25);
        // $pdf->SetY(25);
        $pdf->SetX(25);   
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(0, 0, 0);

        // Get order data
        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $customer_address = $order->get_billing_address_1();
        $customer_postcode_city = $order->get_billing_postcode() . ' ' . $order->get_billing_city();
        $invoice_number = $order->get_order_number();
        $invoice_date = $order->get_date_created()->format('d-m-Y');

        // Output block
        $pdf->MultiCell($pdf->GetPageWidth() - 50, 6, utf8_decode(
            "Naam/Firma : $customer_name\n" .
            "Adres : $customer_address\n" .
            "Postcode : $customer_postcode_city\n\n" .
            "Factuurnummer : 2025-$invoice_number\n" .
            "Datum : $invoice_date"
        ), 0, 'L');

        $pdf->Ln(10);
    
        // // Order Items Table Header
        $usableWidth = $pdf->GetPageWidth() - 50;
        $totalWidth = 200;
        $scalingFactor = $usableWidth / $totalWidth;

        $w_product = 80 * $scalingFactor;
        $w_qty     = 30 * $scalingFactor;
        $w_price   = 30 * $scalingFactor;
        $w_total   = 30 * $scalingFactor;

        $pdf->SetX(25);
        $pdf->SetFont('Arial', 'B', 10);
        // Table headers with top and bottom borders only
        $pdf->Cell($w_product, 10, 'Producten/Diensten', 'T');
        $pdf->Cell($w_qty, 10, 'Aantal', 'T');
        $pdf->Cell($w_price, 10, 'Prijs Exc.', 'T');
        $pdf->Cell($w_price, 10, 'BTW.', 'T');
        $pdf->Cell($w_total, 10, 'Prijs Inc.', 'T');
        $pdf->Ln();

        // Space between header and content
        $pdf->SetFont('Arial', '', 10);

        // Table rows with bottom border only
        foreach ($order->get_items() as $item) {
            $product_name = $item->get_name();
            $quantity = $item->get_quantity();
            $total = number_format($item->get_total() * 1.21, 2);
            $price = number_format($item->get_subtotal(), 2);
            $pdf->SetX(25);
            $pdf->Cell($w_product, 10, $product_name, 'T');
            $pdf->Cell($w_qty, 10, $quantity, 'T');
            $pdf->Cell($w_price, 10, iconv('UTF-8', 'Windows-1252//TRANSLIT', "€" . $price), 'T');
            $pdf->Cell($w_price, 10, "21%", 'T');
            $pdf->Cell($w_total, 10, iconv('UTF-8', 'Windows-1252//TRANSLIT',"€" . $total), 'T');
            $pdf->Ln();
        }

        $payment_method = $order->get_payment_method_title();
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetX(25);
        $pdf->Cell(0, 10, "Het volledige bedrag is betaald via " . $payment_method);
    
        // Totals
        $pdf->Ln(20);
        $pdf->SetFont('Arial', '', 10);
        $labelWidth = 50;   // Width for the left column (label)
        $priceWidth = 20; 
        $cellWidth = $labelWidth + $priceWidth; // Width of the block

        // Set X so it starts on the right side
        $pdf->SetX($pdf->GetPageWidth() - 25 - $cellWidth);
          // Width for the right column (amount)

        $pdf->Cell($labelWidth, 6, 'Exclusief BTW in Euro:', 0, 0, 'L');
        $pdf->Cell($priceWidth, 6, iconv('UTF-8', 'Windows-1252//TRANSLIT', '€' . number_format($order->get_subtotal(), 2)), 0, 1, 'R');
        $pdf->SetX($pdf->GetPageWidth() - 25 - $cellWidth);
        $pdf->Cell($labelWidth, 6, 'BTW Tarief in Euro 21%:', 0, 0, 'L');
        $pdf->Cell($priceWidth, 6, iconv('UTF-8', 'Windows-1252//TRANSLIT', '€' . number_format($order->get_total_tax(), 2)), 0, 1, 'R');
        $pdf->SetX($pdf->GetPageWidth() - 25 - $cellWidth);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($labelWidth, 6, 'Inclusief BTW in Euro:', 0, 0, 'L');
        $pdf->Cell($priceWidth, 6, iconv('UTF-8', 'Windows-1252//TRANSLIT', '€' . number_format($order->get_total(), 2)), 0, 1, 'R');

        $pdf->Ln(2); // Small space
        $pdf->SetX($pdf->GetPageWidth() - 25 - $cellWidth);
        $pdf->Cell($labelWidth, 6, 'Totaal:', 0, 0, 'L');
        $pdf->Cell($priceWidth, 6, iconv('UTF-8', 'Windows-1252//TRANSLIT', '€' . number_format($order->get_total(), 2)), 0, 1, 'R');
        // Save PDF
        $pdf_file = wp_tempnam("factuur_2025-" . $order->get_order_number()) . '.pdf';
        $pdf->Output('F', $pdf_file);
    
        return $pdf_file;
    }
}