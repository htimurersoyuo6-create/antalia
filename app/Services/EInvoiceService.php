<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReservationInvoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class EInvoiceService
{
    public function buildUblXml(ReservationInvoice $invoice): string
    {
        $invoice->loadMissing(['reservation.user', 'hotel']);

        $reservation = $invoice->reservation;
        $hotel = $invoice->hotel;
        $customer = $reservation?->user;

        $uuid = (string) ($invoice->provider_uuid ?: (string) Str::uuid());
        $issueDate = ($invoice->issued_at ?? now())->format('Y-m-d');
        $issueTime = ($invoice->issued_at ?? now())->format('H:i:s');
        $amount = number_format((float) ($invoice->amount ?? 0), 2, '.', '');
        $taxAmount = number_format(round((float) ($invoice->amount ?? 0) * 0.20, 2), 2, '.', '');
        $lineExtension = number_format((float) ($invoice->amount ?? 0), 2, '.', '');
        $invoiceNumber = htmlspecialchars((string) ($invoice->invoice_number ?: 'TASLAK-'.$invoice->id), ENT_XML1, 'UTF-8');
        $hotelName = htmlspecialchars((string) ($hotel->name ?? ''), ENT_XML1, 'UTF-8');
        $hotelVkn = htmlspecialchars((string) ($hotel->license_no ?? '0000000000'), ENT_XML1, 'UTF-8');
        $customerName = htmlspecialchars((string) ($customer->name ?? 'Misafir'), ENT_XML1, 'UTF-8');
        $description = htmlspecialchars('Konaklama hizmeti · '.($reservation?->code ?? ''), ENT_XML1, 'UTF-8');

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
  <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
  <cbc:CustomizationID>TR1.2</cbc:CustomizationID>
  <cbc:ProfileID>TICARIFATURA</cbc:ProfileID>
  <cbc:ID>{$invoiceNumber}</cbc:ID>
  <cbc:UUID>{$uuid}</cbc:UUID>
  <cbc:IssueDate>{$issueDate}</cbc:IssueDate>
  <cbc:IssueTime>{$issueTime}</cbc:IssueTime>
  <cbc:InvoiceTypeCode>SATIS</cbc:InvoiceTypeCode>
  <cbc:DocumentCurrencyCode>{$invoice->currency}</cbc:DocumentCurrencyCode>
  <cac:AccountingSupplierParty>
    <cac:Party>
      <cac:PartyName><cbc:Name>{$hotelName}</cbc:Name></cac:PartyName>
      <cac:PartyIdentification><cbc:ID schemeID="VKN">{$hotelVkn}</cbc:ID></cac:PartyIdentification>
    </cac:Party>
  </cac:AccountingSupplierParty>
  <cac:AccountingCustomerParty>
    <cac:Party>
      <cac:PartyName><cbc:Name>{$customerName}</cbc:Name></cac:PartyName>
    </cac:Party>
  </cac:AccountingCustomerParty>
  <cac:TaxTotal>
    <cbc:TaxAmount currencyID="{$invoice->currency}">{$taxAmount}</cbc:TaxAmount>
  </cac:TaxTotal>
  <cac:LegalMonetaryTotal>
    <cbc:LineExtensionAmount currencyID="{$invoice->currency}">{$lineExtension}</cbc:LineExtensionAmount>
    <cbc:TaxExclusiveAmount currencyID="{$invoice->currency}">{$lineExtension}</cbc:TaxExclusiveAmount>
    <cbc:TaxInclusiveAmount currencyID="{$invoice->currency}">{$amount}</cbc:TaxInclusiveAmount>
    <cbc:PayableAmount currencyID="{$invoice->currency}">{$amount}</cbc:PayableAmount>
  </cac:LegalMonetaryTotal>
  <cac:InvoiceLine>
    <cbc:ID>1</cbc:ID>
    <cbc:InvoicedQuantity unitCode="C62">1</cbc:InvoicedQuantity>
    <cbc:LineExtensionAmount currencyID="{$invoice->currency}">{$lineExtension}</cbc:LineExtensionAmount>
    <cac:Item><cbc:Name>{$description}</cbc:Name></cac:Item>
    <cac:Price><cbc:PriceAmount currencyID="{$invoice->currency}">{$lineExtension}</cbc:PriceAmount></cac:Price>
  </cac:InvoiceLine>
</Invoice>
XML;

        Storage::disk('local')->put('e-invoices/'.$invoice->id.'.xml', $xml);

        return $xml;
    }

    public function send(ReservationInvoice $invoice): ReservationInvoice
    {
        $endpoint = (string) config('services.efatura.endpoint', '');
        $token = (string) config('services.efatura.token', '');

        if ($endpoint === '' || $token === '') {
            throw new RuntimeException('e-Fatura sağlayıcı yapılandırması eksik (EFATURA_ENDPOINT / EFATURA_TOKEN).');
        }

        $xml = $this->buildUblXml($invoice);

        $invoice->forceFill(['e_invoice_status' => 'sending', 'e_invoice_error' => null])->save();

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(20)
                ->post($endpoint, [
                    'invoice_id' => (string) $invoice->id,
                    'invoice_number' => (string) $invoice->invoice_number,
                    'ubl' => base64_encode($xml),
                ]);

            if ($response->failed()) {
                throw new RuntimeException('e-Fatura gönderimi başarısız: HTTP '.$response->status());
            }

            $body = $response->json();

            $invoice->forceFill([
                'e_invoice_status' => 'sent',
                'e_invoice_sent_at' => now(),
                'provider_uuid' => is_array($body) && isset($body['uuid']) ? (string) $body['uuid'] : $invoice->provider_uuid,
                'e_invoice_error' => null,
            ])->save();
        } catch (Throwable $e) {
            $invoice->forceFill([
                'e_invoice_status' => 'failed',
                'e_invoice_error' => mb_substr($e->getMessage(), 0, 255),
            ])->save();

            throw $e;
        }

        return $invoice->refresh();
    }
}
