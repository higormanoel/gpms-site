<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/cms.php';

gpms_force_https();
gpms_security_headers(true);
header('Content-Type: application/json; charset=UTF-8');

function gpms_contact_response(int $status, bool $success, string $message): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    gpms_contact_response(405, false, 'Envie a mensagem pelo formulário do site.');
}

if (trim((string) ($_POST['website'] ?? '')) !== '') {
    gpms_contact_response(200, true, 'Mensagem recebida.');
}

$name = trim((string) ($_POST['nome'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = trim((string) ($_POST['telefone'] ?? ''));
$message = trim((string) ($_POST['mensagem'] ?? ''));
$consent = (string) ($_POST['consentimento'] ?? '');
$nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
$messageLength = function_exists('mb_strlen') ? mb_strlen($message, 'UTF-8') : strlen($message);

if ($nameLength < 2 || $nameLength > 120) {
    gpms_contact_response(422, false, 'Informe seu nome para continuar.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    gpms_contact_response(422, false, 'Informe um email válido.');
}
if ($phone === '' || !preg_match('/^[0-9+()\s.-]{8,30}$/', $phone)) {
    gpms_contact_response(422, false, 'Informe um telefone válido.');
}
if ($messageLength < 20 || $messageLength > 5000) {
    gpms_contact_response(422, false, 'Escreva uma mensagem entre 20 e 5.000 caracteres.');
}
if ($consent !== '1') {
    gpms_contact_response(422, false, 'Confirme a autorização para que a GPMS possa responder.');
}

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$rateKey = hash('sha256', $ip);
$rate = gpms_read_store('contact-rate.php');
$lastSent = (int) ($rate[$rateKey] ?? 0);
if ($lastSent > time() - 60) {
    gpms_contact_response(429, false, 'Aguarde um minuto antes de enviar outra mensagem.');
}

$safeName = preg_replace('/[\r\n]+/', ' ', $name) ?: 'Contato pelo site';
$subject = 'Contato pelo site GPMS - ' . $safeName;
$body = "Novo contato recebido pelo site GPMS\n\n"
    . "Nome: {$name}\n"
    . "Email: {$email}\n"
    . "Telefone: {$phone}\n\n"
    . "Mensagem:\n{$message}\n";
$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'From: Site GPMS <site@gpms.com.br>',
    'Reply-To: ' . $email,
    'X-Mailer: PHP/' . PHP_VERSION,
];

if (!mail('gpms@gpms.com.br', $subject, $body, implode("\r\n", $headers))) {
    gpms_contact_response(503, false, 'Não foi possível enviar agora. Fale conosco por email ou WhatsApp.');
}

$rate[$rateKey] = time();
foreach ($rate as $key => $timestamp) {
    if ((int) $timestamp < time() - 86400) {
        unset($rate[$key]);
    }
}
gpms_write_store('contact-rate.php', $rate);

gpms_contact_response(200, true, 'Mensagem enviada. A GPMS retornará o contato com discrição.');
