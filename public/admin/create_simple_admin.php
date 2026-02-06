<?php
// Create a simple admin with password: admin123

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Delete any existing test admin
$delete = "DELETE FROM admins WHERE username = 'test'";
$db->exec($delete);

// Create new admin with simple password
$username = 'test';
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);
$email = 'test@partycarpool.com';

$query = "INSERT INTO admins (username, password_hash, email) VALUES (:username, :hash, :email)";
$stmt = $db->prepare($query);
$stmt->bindParam(':username', $username);
$stmt->bindParam(':hash', $hash);
$stmt->bindParam(':email', $email);

if ($stmt->execute()) {
    echo "✓ Test admin created successfully!\n";
    echo "Username: test\n";
    echo "Password: admin123\n\n";
} else {
    echo "Failed to create test admin\n";
}

// Also ensure the main admin works
$admin_password = 'Admin2024!';
$admin_hash = password_hash($admin_password, PASSWORD_DEFAULT);

$update = "UPDATE admins SET password_hash = :hash WHERE username = 'admin'";
$stmt2 = $db->prepare($update);
$stmt2->bindParam(':hash', $admin_hash);

if ($stmt2->execute()) {
    echo "✓ Main admin password also reset!\n";
    echo "Username: admin\n";
    echo "Password: Admin2024!\n\n";
    echo "You can now login at: https://partycarpool.clodhost.com/admin/login.php\n";
} else {
    echo "Failed to update main admin\n";
}

// List all admins
echo "\nAll admin accounts:\n";
$list = "SELECT username, email FROM admins";
$result = $db->query($list);
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "- " . $row['username'] . " (" . $row['email'] . ")\n";
}
?>