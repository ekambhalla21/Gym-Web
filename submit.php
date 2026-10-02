<?php

// Gym Membership Form Backend
// No vendor folder or PhpSpreadsheet required.
// Saves members into members.csv and redirects to success.html.

$csvFile = __DIR__ . '/members.csv';

$headers = [
    'Member ID',
    'First Name',
    'Last Name',
    'Email',
    'Phone Number',
    'Date of Birth',
    'Gender',
    'Membership Plan',
    'Preferred Workout Time',
    'Fitness Goal',
    'Fitness Experience',
    'Additional Information',
    'Registration Date'
];

function clean($value): string
{
    return trim((string)($value ?? ''));
}

function normalizePhone($phone): string
{
    return preg_replace('/\D+/', '', (string)$phone);
}

function showError(string $title, string $message, int $statusCode = 400): never
{
    http_response_code($statusCode);

    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 80px 20px;
            background: #f5effb;
        }

        .box {
            max-width: 520px;
            margin: auto;
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,.15);
        }

        h1 {
            color: #7b2cbf;
        }

        a {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: #7b2cbf;
            color: white;
            text-decoration: none;
            border-radius: 8px;
        }
    </style>
</head>

<body>

<div class="box">

    <h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>

    <p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>

    <a href="index.html">Back to Form</a>

</div>

</body>
</html>';

    exit;
}


// Only accept POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html');
    exit;
}


// Get form values.
$firstName   = clean($_POST['firstName'] ?? '');
$lastName    = clean($_POST['lastName'] ?? '');
$email       = clean($_POST['email'] ?? '');
$phone       = clean($_POST['phone'] ?? '');
$dob         = clean($_POST['dob'] ?? '');
$gender      = clean($_POST['gender'] ?? '');
$membership  = clean($_POST['membership'] ?? '');
$workoutTime = clean($_POST['workoutTime'] ?? '');
$goal        = clean($_POST['goal'] ?? '');
$experience  = clean($_POST['experience'] ?? '');
$message     = clean($_POST['message'] ?? '');


// Validate required fields.
$required = [
    $firstName,
    $lastName,
    $email,
    $phone,
    $dob,
    $gender,
    $membership,
    $goal
];

if (in_array('', $required, true)) {
    showError(
        'Missing Information',
        'Please complete all required fields before submitting the form.'
    );
}


// Validate email.
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    showError(
        'Invalid Email',
        'Please enter a valid email address.'
    );
}


try {

    // Create CSV file if it does not exist.
    if (!file_exists($csvFile)) {

        $file = fopen($csvFile, 'w');

        if ($file === false) {
            throw new Exception('Unable to create CSV file.');
        }

        fputcsv($file, $headers);

        fclose($file);
    }


    // Read existing members for duplicate checking.
    $file = fopen($csvFile, 'r');

    if ($file === false) {
        throw new Exception('Unable to open CSV file.');
    }

    // Skip header row.
    fgetcsv($file);

    $submittedEmail = strtolower($email);
    $submittedPhone = normalizePhone($phone);

    while (($row = fgetcsv($file)) !== false) {

        if (count($row) < 13) {
            continue;
        }

        // Email is column 4.
        $existingEmail = strtolower(clean($row[3]));

        // Phone is column 5.
        $existingPhone = normalizePhone(clean($row[4]));


        // Duplicate email.
        if (
            $submittedEmail !== '' &&
            $existingEmail === $submittedEmail
        ) {

            fclose($file);

            showError(
                'Already Registered',
                'A member with this email address already exists.',
                409
            );
        }


        // Duplicate phone.
        if (
            $submittedPhone !== '' &&
            $existingPhone === $submittedPhone
        ) {

            fclose($file);

            showError(
                'Already Registered',
                'A member with this phone number already exists.',
                409
            );
        }
    }

    fclose($file);


    // Generate unique member ID.
    do {

        $memberId = 'GYM-' . strtoupper(
            bin2hex(random_bytes(4))
        );

        $idExists = false;

        $file = fopen($csvFile, 'r');

        if ($file === false) {
            throw new Exception('Unable to open CSV file.');
        }

        // Skip header.
        fgetcsv($file);

        while (($row = fgetcsv($file)) !== false) {

            if (
                isset($row[0]) &&
                clean($row[0]) === $memberId
            ) {
                $idExists = true;
                break;
            }
        }

        fclose($file);

    } while ($idExists);


    // Registration date.
    $registrationDate = date('Y-m-d H:i:s');


    // Prepare new member.
    $data = [
        $memberId,
        $firstName,
        $lastName,
        $email,
        $phone,
        $dob,
        $gender,
        $membership,
        $workoutTime,
        $goal,
        $experience,
        $message,
        $registrationDate
    ];


    // Append new member.
    $file = fopen($csvFile, 'a');

    if ($file === false) {
        throw new Exception('Unable to save member.');
    }

    // Lock file to prevent simultaneous writes.
    if (flock($file, LOCK_EX)) {

        fputcsv($file, $data);

        fflush($file);

        flock($file, LOCK_UN);

    } else {

        fclose($file);

        throw new Exception('Unable to lock member database.');
    }

    fclose($file);


    // Registration successful.
    header('Location: success.html');
    exit;


} catch (Throwable $e) {

    showError(
        'Server Error',
        'The registration could not be saved. Please check that the folder is writable.',
        500
    );
}

?>
