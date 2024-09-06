<?php

namespace App\Controller\Gandaal\Administration\Scolarite;

use App\Entity\Eleve;
use App\Entity\Inscription;
use App\Entity\DocumentEleve;
use App\Entity\Etablissement;
use App\Form\DocumentEleveType;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\InscriptionRepository;
use App\Repository\DocumentEleveRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/administration/scolarite/document/eleve')]
class DocumentEleveController extends AbstractController
{
    #[Route('/', name: 'app_gandaal_administration_scolarite_document_eleve_index', methods: ['GET'])]
    public function index(DocumentEleveRepository $documentEleveRepository): Response
    {
        return $this->render('gandaal/administration/scolarite/document_eleve/index.html.twig', [
            'document_eleves' => $documentEleveRepository->findAll(),
        ]);
    }

    #[Route('/new/{eleve}/{etablissement}', name: 'app_gandaal_administration_scolarite_document_eleve_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Eleve $eleve, InscriptionRepository $inscriptionRep, Etablissement $etablissement, SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        $documentEleve = new DocumentEleve();
        $form = $this->createForm(DocumentEleveType::class, $documentEleve);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fichier_document = $form->get("nom")->getData();
            if ($fichier_document) {
                $nomFichier= pathinfo($fichier_document->getClientOriginalName(), PATHINFO_FILENAME);
                $slugger = new AsciiSlugger();
                $nouveauNomFichier = $slugger->slug($nomFichier);
                $nouveauNomFichier .="_".uniqid();
                $nouveauNomFichier .= "." .$fichier_document->guessExtension();
                $fichier_document->move($this->getParameter("dossier_eleves"), $nouveauNomFichier);
                $documentEleve->setNom($nouveauNomFichier)
                        ->setEleve($eleve);
                $entityManager->persist($documentEleve);

            }            
            $entityManager->persist($documentEleve);
            $entityManager->flush();

            $inscription = $inscriptionRep->findOneBy(['eleve' => $eleve, 'promo' => $session->get('promo')]);

            $this->addFlash("success", "Document ajouté avec succès :)");
            return $this->redirectToRoute('app_gandaal_administration_scolarite_eleve_show', ['etablissement' => $etablissement->getId(), 'id' => $inscription->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/scolarite/document_eleve/new.html.twig', [
            'document_eleve' => $documentEleve,
            'form' => $form,
            'etablissement' => $etablissement,
            'eleve' => $eleve,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_administration_scolarite_document_eleve_show', methods: ['GET'])]
    public function show(DocumentEleve $documentEleve): Response
    {
        return $this->render('gandaal/administration/scolarite/document_eleve/show.html.twig', [
            'document_eleve' => $documentEleve,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gandaal_administration_scolarite_document_eleve_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, DocumentEleve $documentEleve, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DocumentEleveType::class, $documentEleve);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_scolarite_document_eleve_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/scolarite/document_eleve/edit.html.twig', [
            'document_eleve' => $documentEleve,
            'form' => $form,
        ]);
    }

    #[Route('/delete/{id}/{etablissement}', name: 'app_gandaal_administration_scolarite_document_eleve_delete', methods: ['POST'])]
    public function delete(Request $request, Etablissement $etablissement, InscriptionRepository $inscriptionRep, DocumentEleve $documentEleve, SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$documentEleve->getId(), $request->getPayload()->getString('_token'))) {
            if ($documentEleve->getNom()) {
                $ancienFichier=$this->getParameter("dossier_eleves")."/".$documentEleve->getNom();
                if (file_exists($ancienFichier)) {
                    unlink($ancienFichier);
                }
            }
            $entityManager->remove($documentEleve);
            $entityManager->flush();
        }
        $inscription = $inscriptionRep->findOneBy(['eleve' => $documentEleve->getEleve(), 'promo' => $session->get('promo')]);

        $this->addFlash("success", "Document supprimé avec succès :)");
        return $this->redirectToRoute('app_gandaal_administration_scolarite_eleve_show', ['etablissement' => $etablissement->getId(), 'id' => $inscription->getId()], Response::HTTP_SEE_OTHER);
    }
}
