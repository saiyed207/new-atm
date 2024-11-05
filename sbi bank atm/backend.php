<?php
session_start();

// Database connection
$host = 'localhost'; // Your database host
$db = 'sbi';        // Your database name
$user = 'root';     // Your database username
$pass = '';         // Your database password

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    // Set Content-Type to JSON and return error as JSON
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]);
    exit();
}

// Function to sanitize input
function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Set Content-Type to JSON for all responses
header('Content-Type: application/json');

// Handle account number submission
if (isset($_POST['submit_account'])) {
    $account_number = sanitize_input($_POST['account_number']);

    // Query to check account number
    $stmt = $conn->prepare("SELECT * FROM accounts WHERE account_number = ?");
    $stmt->bind_param("s", $account_number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Account exists, store account id in session
        $account = $result->fetch_assoc();
        $_SESSION['account_id'] = $account['id'];
        $_SESSION['account_number'] = $account_number;
        echo json_encode(['success' => true, 'message' => 'Account found.']);
    } else {
        echo json_encode(['success' => false, 'message' => "Error: Account number is incorrect."]);
    }
    exit();
}

// Handle password submission
if (isset($_POST['submit_password'])) {
    $entered_pin = sanitize_input($_POST['pin']);
    $account_id = $_SESSION['account_id'];

    // Query to check pin
    $stmt = $conn->prepare("SELECT * FROM accounts WHERE id = ? AND pin = ?");
    $stmt->bind_param("is", $account_id, $entered_pin);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'PIN verified.']);
    } else {
        echo json_encode(['success' => false, 'message' => "Error: PIN is incorrect."]);
    }
    exit();
}

// Handle balance inquiry
if (isset($_POST['balance_inquiry'])) {
    if (isset($_SESSION['account_id'])) {
        $account_id = $_SESSION['account_id'];

        $stmt = $conn->prepare("SELECT account_holder_name, account_number, account_balance FROM accounts WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $account_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                echo json_encode([
                    'success' => true,
                    'holder' => $row['account_holder_name'],
                    'number' => $row['account_number'],
                    'balance' => $row['account_balance']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => "Error: Unable to retrieve balance."]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => "Error: Failed to prepare statement."]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => "Error: Account ID not set in session."]);
    }
    exit();
}
// Handle deposit submission
if (isset($_POST['deposit'])) {
    $account_id = $_SESSION['account_id'];
    $deposit_amount = sanitize_input($_POST['deposit']);

    // Validate the deposit amount
    if (!is_numeric($deposit_amount) || $deposit_amount <= 0) {
        echo json_encode(['success' => false, 'message' => "Error: Please enter a valid deposit amount."]);
        exit();
    }

    // Query to update the account balance
    $stmt = $conn->prepare("UPDATE accounts SET account_balance = account_balance + ? WHERE id = ?");
    $stmt->bind_param("di", $deposit_amount, $account_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Deposit successful!']);
    } else {
        echo json_encode(['success' => false, 'message' => "Error: Unable to process deposit."]);
    }
    exit();
}

// Handle withdrawal submission
if (isset($_POST['withdraw_amount'])) {
    $account_id = $_SESSION['account_id'];
    $withdraw_amount = sanitize_input($_POST['withdraw_amount']);

    // Validate the withdrawal amount
    if (!is_numeric($withdraw_amount) || $withdraw_amount <= 0) {
        echo json_encode(['success' => false, 'message' => "Error: Please enter a valid withdrawal amount."]);
        exit();
    }

    // Query to check account balance
    $stmt = $conn->prepare("SELECT account_balance FROM accounts WHERE id = ?");
    $stmt->bind_param("i", $account_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $current_balance = $row['account_balance'];

        // Check if there are sufficient funds
        if ($withdraw_amount > $current_balance) {
            echo json_encode(['success' => false, 'message' => "Error: Insufficient funds."]);
            exit();
        }

        // Update account balance
        $new_balance = $current_balance - $withdraw_amount;
        $stmt = $conn->prepare("UPDATE accounts SET account_balance = ? WHERE id = ?");
        $stmt->bind_param("di", $new_balance, $account_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Withdrawal successful!']);
        } else {
            echo json_encode(['success' => false, 'message' => "Error: Unable to process withdrawal."]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => "Error: Unable to retrieve account balance."]);
    }
    exit();
}

// Handle change PIN submission
if (isset($_POST['change_pin'])) {
    $new_pin = $_POST['new_pin'];
    $account_number = $_SESSION['account_number']; // Assuming a session holds the account number

    // Validate and update the PIN (use prepared statements for security)
    $stmt = $conn->prepare("UPDATE accounts SET pin = ? WHERE account_number = ?");
    $stmt->bind_param("si", $new_pin, $account_number);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'PIN changed successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to change PIN. Please try again.']);
    }
    $stmt->close();
    exit;
}

if (isset($_POST['fetch_balance'])) {
    $account_number = $_SESSION['account_number']; // Assuming the session holds the user's account number
    $balance = getAccountBalance($account_number); // Replace with your actual function to fetch the balance
    echo json_encode(['success' => true, 'balance' => $balance]);
    exit;
}


$conn->close();
?>
