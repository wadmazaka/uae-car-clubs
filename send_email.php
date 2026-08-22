<?php
$data = json_decode(file_get_contents("php://input"), true);

$name = $data['name'];
$email = $data['email'];
$phone = $data['phone'];
$message = $data['message'];

$to = "wadmazaka1@gmail.com";
$subject = "New Message from UCC Form";

$body = "Name: $name\nEmail: $email\nPhone: $phone\nMessage: $message";

$headers = "From: noreply@yourdomain.com";

if(mail($to, $subject, $body, $headers)){
    echo "Email sent successfully!";
} else {
    echo "Failed to send email.";
}
?>