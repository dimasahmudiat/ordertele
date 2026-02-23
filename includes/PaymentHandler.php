<?php
/**
 * Payment Handler
 */

class PaymentHandler {
    
    /**
     * Create payment
     */
    public function createPayment($orderId, $amount) {
        $url = "https://cekid-ariepulsa.my.id/qris/?action=get-deposit&kode=" . urlencode($orderId) . "&nominal=" . $amount . "&apikey=" . API_KEY;
        
        logMessage("Creating payment: " . $url);
        $response = file_get_contents($url);
        logMessage("Payment response: " . $response);
        
        return json_decode($response, true);
    }
    
    /**
     * Check payment status
     */
    public function checkPaymentStatus($depositCode) {
        $url = "https://cekid-ariepulsa.my.id/qris/?action=get-mutasi&kode=" . urlencode($depositCode) . "&apikey=" . API_KEY;
        
        logMessage("Checking payment status: " . $url);
        $response = file_get_contents($url);
        $data = json_decode($response, true);
        
        logMessage("Payment status response: " . $response);
        
        if ($data && $data['status'] && isset($data['data']['status']) && $data['data']['status'] == 'Success') {
            return $data['data'];
        }
        
        return false;
    }
}
