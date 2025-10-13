<?php

namespace App\Service;

use App\Domain\AppConstants;
use App\Entity\User;
use App\Service\Utility\Base64UrlHelper;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

readonly class EmailService
{
    /**
     * @param  MailerInterface  $mailer
     */
    public function __construct(
        private MailerInterface $mailer
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendInvitationEmail(User $user): void
    {
        // Create the registration link with the verification token
        $token = Base64UrlHelper::encode($user->getVerificationToken());
        $registrationLink = AppConstants::$frontendBaseUri . '/register/' . $token;

        $email = new Email()->from(AppConstants::$mailerFromAddress)
                            ->to($user->getEmail())
                            ->subject('Welcome to YAPM - Complete Your Registration')
                            ->html($this->getInvitationEmailTemplate($user->getUsername(), $registrationLink));

        $this->mailer->send($email);
    }

    /**
     * Generate the email template for the invitation email.
     *
     * @param  string  $userName
     * @param  string  $registrationLink
     *
     * @return string
     */
    private function getInvitationEmailTemplate(string $userName, string $registrationLink): string
    {
        return '
            <html lang="en-en">
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #38a169; color: white; padding: 20px; text-align: center; }
                        .content { padding: 20px; }
                        .button { display: inline-block; background-color: #38a169; color: white; padding: 10px 20px; 
                                  text-decoration: none; border-radius: 5px; margin-top: 20px; }
                        .footer { margin-top: 30px; font-size: 12px; color: #666; text-align: center; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>Welcome to YAPM</h1>
                        </div>
                        <div class="content">
                            <p>Hello ' . htmlspecialchars($userName) . ',</p>
                            <p>You have been invited to join YAPM - Yet Another Password Manager. To complete your registration and set your password, please click the button below:</p>
                            <p>
                                <a href="' . htmlspecialchars($registrationLink) . '" class="button">Complete Registration</a>
                            </p>
                            <p>If the button doesn\'t work, copy and paste this link into your browser:</p>
                            <p>' . htmlspecialchars($registrationLink) . '</p>
                            <p>Thank you,<br>The YAPM Team</p>
                        </div>
                        <div class="footer">
                            <p>This is an automated message, please do not reply to this email.</p>
                        </div>
                    </div>
                </body>
            </html>
        ';
    }
}
