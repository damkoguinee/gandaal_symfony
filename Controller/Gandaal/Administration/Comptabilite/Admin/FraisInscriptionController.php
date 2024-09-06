<?php

namespace App\Controller\Gandaal\Administration\Comptabilite\Admin;

use App\Entity\Etablissement;
use App\Entity\FraisInscription;
use App\Form\FraisInscriptionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\FraisInscriptionRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[Route('/gandaal/administration/comptabilite/admin/frais/inscription')]
class FraisInscriptionController extends AbstractController
{
    #[Route('/{etablissement}', name: 'app_gandaal_administration_comptabilite_admin_frais_inscription_index', methods: ['GET'])]
    public function index(FraisInscriptionRepository $fraisInscriptionRepository, SessionInterface $session, Etablissement $etablissement): Response
    {
        return $this->render('gandaal/administration/comptabilite/admin/frais_inscription/index.html.twig', [
            'frais_inscriptions' => $fraisInscriptionRepository->findBy(['etablissement' => $etablissement, 'promo' => $session->get('promo')], ['cursus' => 'ASC']),
            'etablissement' => $etablissement
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_administration_comptabilite_admin_frais_inscription_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SessionInterface $session, Etablissement $etablissement): Response
    {
        $promo = $session->get('promo');
        $fraisInscription = new FraisInscription();
        $form = $this->createForm(FraisInscriptionType::class, $fraisInscription, ['etablissement' => $etablissement]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fraisInscription->setPromo($promo)
                    ->setEtablissement($etablissement);
            $entityManager->persist($fraisInscription);
            $entityManager->flush();

            $this->addFlash("success", "frais ajouté avec succès :) ");

            return $this->redirectToRoute('app_gandaal_administration_comptabilite_admin_frais_inscription_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/comptabilite/admin/frais_inscription/new.html.twig', [
            'frais_inscription' => $fraisInscription,
            'form' => $form,
            'etablissement' => $etablissement
        ]);
    }

    #[Route('/show/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_admin_frais_inscription_show', methods: ['GET'])]
    public function show(FraisInscription $fraisInscription, Etablissement $etablissement): Response
    {
        return $this->render('gandaal/administration/comptabilite/admin/frais_inscription/show.html.twig', [
            'frais_inscription' => $fraisInscription,
            'etablissement' => $etablissement
        ]);
    }

    #[Route('/edit/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_admin_frais_inscription_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, FraisInscription $fraisInscription, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        $form = $this->createForm(FraisInscriptionType::class, $fraisInscription, ['etablissement' => $etablissement]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_comptabilite_admin_frais_inscription_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/comptabilite/admin/frais_inscription/edit.html.twig', [
            'frais_inscription' => $fraisInscription,
            'form' => $form,
            'etablissement' => $etablissement
        ]);
    }

    #[Route('/delete/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_admin_frais_inscription_delete', methods: ['POST'])]
    public function delete(Request $request, FraisInscription $fraisInscription, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        if ($this->isCsrfTokenValid('delete'.$fraisInscription->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($fraisInscription);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_administration_comptabilite_admin_frais_inscription_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }
}
