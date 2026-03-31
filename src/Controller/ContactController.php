<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\Liste;
use App\Form\ContactType;
use App\Repository\ContactRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/contact')]
final class ContactController extends AbstractController
{
    #[Route(name: 'app_contact_index', methods: ['GET'])]
    public function index(ContactRepository $contactRepository): Response
    {
        return $this->render('contact/index.html.twig', [
            'contacts' => $contactRepository->findAll(),
        ]);
    }

    #[Route('/new/{adherent_id}', name: 'app_contact_new', methods: ['GET', 'POST'])]
    public function new(
        int $adherent_id,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $adherent = $entityManager->getRepository(Liste::class)->find($adherent_id);

        if (!$adherent) {
            throw $this->createNotFoundException('Adhérent non trouvé');
        }

        $contact = new Contact();
        $contact->setListe($adherent);

        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($contact);
            $entityManager->flush();

            return $this->redirectToRoute('app_liste_show', ['id' => $adherent_id]);
        }

        return $this->render('contact/new.html.twig', [
            'contact' => $contact,
            'adherent' => $adherent,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_contact_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Contact $contact,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_liste_show', ['id' => $contact->getListe()->getId()]);
        }

        return $this->render('contact/edit.html.twig', [
            'contact' => $contact,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_contact_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Contact $contact,
        EntityManagerInterface $entityManager
    ): Response {
        $adherentId = $contact->getListe()->getId();
        if ($this->isCsrfTokenValid('delete' . $contact->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($contact);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_liste_show', ['id' => $adherentId]);
    }

    #[Route('/{id}/contacts', name: 'app_liste_contacts_interne', methods: ['GET'])]
    public function showContacts(Liste $liste): Response
    {
        return $this->render('liste/contacts_interne.html.twig', [
            'liste' => $liste,
        ]);
    }
}