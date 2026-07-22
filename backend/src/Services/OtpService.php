<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\User;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Cache\CacheItemPoolInterface;
use Twig\Environment as TwigEnvironment;

class OtpService
{
    public function __construct(
        private UserRepository $userRepository,
        private TwigEnvironment $twig,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function sendOtp(string $email): bool
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            return false;
        }

        $code = random_int(100000, 999999);

        try {
            $this->sendOtpByEmail($code, $email, $user);
        } catch (\Throwable $e) {
            $this->logger->error('OTP dispatch error: ', ['message' => $e->getMessage()]);

            return false;
        }

        $this->saveOtp($email, $code);

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

    private function saveOtp(string $email, int $code): void
    {
        $item = $this->cache->getItem('otp_' . $email);

        $item->set($code);
        $item->expiresAfter(300);

        $this->cache->save($item);
    }

    private function deleteOtp(string $email): void
    {
        $this->cache->deleteItem('otp_' . $email);
    }

    public function verificationOtp(string $email, string $code): bool
    {
        $item = $this->cache->getItem('otp_' . $email);

        if (!$item->isHit()) {
            return false;
        }

        $isValid = (int) $item->get() === (int) $code;
        $this->deleteOtp($email);

        return $isValid;
    }
}
