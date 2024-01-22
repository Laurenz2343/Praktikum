<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Contact;
use App\FormType\ContactType;
use App\Service\EmailService;
use App\Service\HashService;
use App\Service\QueryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IndexController extends AbstractController
{
    public const CV_ASSET_DIR = '../uploads/assets';
    public const CV_ASSET_FILENAME = 'Steckbrief.pdf';

    protected $receiverEmailAddress;

    protected EmailService $emailService;

    protected HashService $hashService;

    public function __construct(EmailService $emailService, HashService $hashService, string $receiverEmailAddress)
    {
        $this->emailService = $emailService;
        $this->hashService = $hashService;
        $this->receiverEmailAddress = $receiverEmailAddress;
    }

    /**
     * @Route("/", name="home")
     */
    public function index(Request $request, QueryService $queryService, Filesystem $filesystem): Response
    {
        $showDownloadButton = false;
        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->emailService->sendMail($this->receiverEmailAddress,
                    $this->receiverEmailAddress,
                    $contact->getSubject(),
                    'emails/contact.html.twig',
                    [
                        'name' => $contact->getName(),
                        'from' => $contact->getEmail(),
                        'message' => $contact->getMessage(),
                    ]);
                $this->addFlash('contact-success', 'Ihre Nachricht wurde erfolgreich gesendet!');
            } catch (\Exception $e) {
                $this->addFlash('contact-danger', 'Ihre Nachricht kann derzeit nicht zugestellt werden!');
                return $this->renderForm('landingpage/index.html.twig', [
                    'showDownloadButton' => $showDownloadButton,
                    'form' => $form,
                ]);
            }
            return $this->redirectToRoute('home');
        }

        $showDownloadButton = $this->hashService->validateHash($request);
        
        return $this->renderForm('landingpage/index.html.twig', [
            'form' => $form,
            'showDownloadButton' => $showDownloadButton,
            'hash' => $this->hashService->getHash($request),
        ]);
    }

    /**
     * @Route("/downloadCv", name="downloadCv")
     */
    public function getUploadedCv(Filesystem $filesystem, Request $request): Response
    {
        if (!$this->hashService->validateHash($request)) {
            return new Response('Not authorized', Response::HTTP_FORBIDDEN);
        }

        $filename = self::CV_ASSET_DIR . \DIRECTORY_SEPARATOR . self::CV_ASSET_FILENAME;
        if (!$filesystem->exists($filename)) {
            return new Response('File not set yet!', Response::HTTP_NOT_FOUND);
        }
        return new BinaryFileResponse($filename);
    }
}
