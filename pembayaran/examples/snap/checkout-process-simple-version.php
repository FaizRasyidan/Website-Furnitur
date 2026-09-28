<?php
// This is just for very basic implementation reference, in production, you should validate the incoming requests and implement your backend more securely.
// Please refer to this docs for snap popup:
// https://docs.midtrans.com/en/snap/integration-guide?id=integration-steps-overview

namespace Midtrans;

require_once dirname(__FILE__) . '/../../Midtrans.php';
$midtransCredentials = require_once dirname(__FILE__) . '/../../midtrans-credentials.php';
Config::$serverKey = $midtransCredentials['serverKey'];
Config::$clientKey = $midtransCredentials['clientKey'];
Config::$isProduction = $midtransCredentials['isProduction'];

// non-relevant function only used for demo/example purpose
Config::$isSanitized = Config::$is3ds = true;

function outputJsonError($message, $code = 500) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (strpos(Config::$serverKey, '<Mid-server-') !== false || strpos(Config::$clientKey, '<Mid-client-') !== false) {
        outputJsonError('Midtrans keys are not configured. Please set serverKey and clientKey in pembayaran/midtrans-credentials.php.', 500);
    }

    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['gross_amount'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request body']);
        exit;
    }

    $transaction_details = array(
        'order_id' => $input['order_id'] ?? 'ORDER-' . time(),
        'gross_amount' => intval($input['gross_amount']),
    );

    $item_details = array_map(function ($item) {
        return array(
            'id' => $item['id'] ?? 'item-' . rand(),
            'price' => intval($item['price'] ?? 0),
            'quantity' => intval($item['quantity'] ?? 1),
            'name' => $item['name'] ?? 'Produk',
        );
    }, $input['item_details'] ?? array());

    $customer_details = array(
        'first_name' => $input['customer_details']['first_name'] ?? 'Customer',
        'last_name' => $input['customer_details']['last_name'] ?? '',
        'email' => $input['customer_details']['email'] ?? '',
        'phone' => $input['customer_details']['phone'] ?? '',
    );

    $transaction = array(
        'transaction_details' => $transaction_details,
        'customer_details' => $customer_details,
        'item_details' => $item_details,
    );

    try {
        $snap_token = Snap::getSnapToken($transaction);
        echo json_encode(['snap_token' => $snap_token]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Required
$transaction_details = array(
    'order_id' => rand(),
    'gross_amount' => 94000, // no decimal allowed for creditcard
);
// Optional
$item_details = array (
    array(
        'id' => 'a1',
        'price' => 94000,
        'quantity' => 1,
        'name' => "Apple"
    ),
  );
// Optional
$customer_details = array(
    'first_name'    => "Andri",
    'last_name'     => "Litani",
    'email'         => "andri@litani.com",
    'phone'         => "081122334455",
    'billing_address'  => $billing_address,
    'shipping_address' => $shipping_address
);
// Fill transaction details
$transaction = array(
    'transaction_details' => $transaction_details,
    'customer_details' => $customer_details,
    'item_details' => $item_details,
);

$snap_token = '';
try {
    $snap_token = Snap::getSnapToken($transaction);
}
catch (\Exception $e) {
    echo $e->getMessage();
}
echo "snapToken = ".$snap_token;

function printExampleWarningMessage() {
    if (strpos(Config::$serverKey, '<Mid-server-') !== false || strpos(Config::$clientKey, '<Mid-client-') !== false) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            outputJsonError('Midtrans keys are not configured. Please set serverKey and clientKey in pembayaran/midtrans-credentials.php.', 500);
        }
        echo "<code>";
        echo "<h4>Please set your Midtrans server key and client key</h4>";
        echo "In file: " . __FILE__;
        echo "<br>";
        echo "<br>";
        echo htmlspecialchars('Please replace placeholder values in pembayaran/midtrans-credentials.php or set MIDTRANS_SERVER_KEY and MIDTRANS_CLIENT_KEY environment variables.');
        die();
    }
}

?>

<!DOCTYPE html>
<html>
    <body>
        <button id="pay-button">Pay!</button>
        <!-- TODO: Remove ".sandbox" from script src URL for production environment. Also input your client key in "data-client-key" -->
        <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="<?php echo Config::$clientKey;?>"></script>
        <script type="text/javascript">
            document.getElementById('pay-button').onclick = function(){
                // SnapToken acquired from previous step
                snap.pay('<?php echo $snap_token?>');
            };
        </script>
    </body>
</html>
