<?php
// Simple QR Code Generator using external API (no dependencies required)

class QRGenerator {
  public static function generateQRCode($data, $size = 200) {
    // Use QR Server API (free, no dependencies)
    $encoded = urlencode($data);
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encoded}";
  }
  
  public static function generateQRCodeBase64($data, $size = 200) {
    // Generate QR code and encode as base64 for embedding
    $qr_url = self::generateQRCode($data, $size);
    $qr_data = file_get_contents($qr_url);
    return 'data:image/png;base64,' . base64_encode($qr_data);
  }
}
?>
