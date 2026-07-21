<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment as TwigEnvironment;

class OtpService
{
    public function __construct(
        private UserRepository $userRepository,
        private TwigEnvironment $twig,
        private MailerInterface $mailer
    ){
    }

    public function sendOtp(string $email): bool
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user){
            return false;
        }

        $code = random_int(100000, 999999);

        try {
            $this->sendOtpByEmail($code, $email, $user);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    private function sendOtpByEmail(int $code, string $email, User $user): void
    {
        $html = $this->twig->render('otp-auth/email-otp-template.html.twig', [
            'code' => $code,
            'user_name' => $user->getName()
        ]);

        $message = (new Email())
            ->from('no-reply@diakonov-it.com.ua')
            ->to($email)
            ->subject('Your confirmation code')
            ->html($html);

        $this->mailer->send($message);
    }
}
