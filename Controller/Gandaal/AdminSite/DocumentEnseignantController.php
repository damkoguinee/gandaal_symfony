<?php

namespace App\Controller\Gandaal\AdminSite;

use App\Entity\Eleve;
use App\Entity\Etablissement;
use App\Entity\DocumentEnseignant;
use App\Entity\Enseignant;
use App\Form\DocumentEnseignantType;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\InscriptionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\DocumentEnseignantRepository;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/admin/site/document/enseignant')]
class DocumentEnseignantController extends AbstractController
{
    #[Route('/', name: 'app_gandaal_admin_site_document_enseignant_index', methods: ['GET'])]
    public function index(DocumentEnseignantRepository $documentEnseignantRepository): Response
    {
        return $this->render('gandaal/admin_site/document_enseignant/index.html.twig', [
            'document_enseignants' => $documentEnseignantRepository->findAll(),
        ]);
    }

    #[Route('/new/{enseignant}/{etablissement}', name: 'app_gandaal_admin_site_document_enseignant_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Enseignant $enseignant, Etablissement $etablissement, SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        $documentEnseignant = new DocumentEnseignant();
        $form = $this->createForm(DocumentEnseignantType::class, $documentEnseignant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fichier_document = $form->get("nom")->getData();
            if ($fichier_document) {
                $nomFichier= pathinfo($fichier_document->getClientOriginalName(), PATHINFO_FILENAME);
                $slugger = new AsciiSlugger();
                $nouveauNomFichier = $slugger->slug($nomFichier);
                $nouveauNomFichier .="_".uniqid();
                $nouveauNomFichier .= "." .$fichier_document->guessExtension();
                $fichier_document->move($this->getParameter("dossier_enseignants"), $nouveauNomFichier);
                $documentEnseignant->setNom($nouveauNomFichier)
                        ->setEnseignant($enseignant);
                $entityManager->persist($documentEnseignant);

            }            
            $entityManager->persist($documentEnseignant);
            $entityManager->flush();

            $this->addFlash("success", "Document ajouté avec succès :)");
            return $this->redirectToRoute('app_gandaal_admin_site_enseignant_show', ['id' => $enseignant->getId(), 'etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin_site/document_enseignant/new.html.twig', [
            'document_eleve' => $documentEnseignant,
            'form' => $form,
            'etablissement' => $etablissement,
            'enseignant' => $enseignant,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_admin_site_document_enseignant_show', methods: ['GET'])]
    public function show(DocumentEnseignant $documentEnseignant): Response
    {
        return $this->render('gandaal/admin_site/document_enseignant/show.html.twig', [
            'document_enseignant' => $documentEnseignant,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gandaal_admin_site_document_enseignant_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, DocumentEnseignant $documentEnseignant, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DocumentEnseignantType::class, $documentEnseignant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_admin_site_document_enseignant_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin_site/document_enseignant/edit.html.twig', [
            'document_enseignant' => $documentEnseignant,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/{etablissement}', name: 'app_gandaal_admin_site_document_enseignant_delete', methods: ['POST'])]
    public function delete(Request $request, DocumentEnseignant $documentEnseignant, Etablissement $etablissement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$documentEnseignant->getId(), $request->getPayload()->getString('_token'))) {
            if ($documentEnseignant->getNom()) {
                $ancienFichier=$this->getParameter("dossier_enseignants")."/".$documentEnseignant->getNom();
                if (file_exists($ancienFichier)) {
                    unlink($ancienFichier);
                }
            }
            $entityManager->remove($documentEnseignant);
            $entityManager->flush();
        }

        $this->addFlash("success", "Document supprimé avec succès :)");
        return $this->redirectToRoute('app_gandaal_admin_site_enseignant_show', ['id' => $documentEnseignant->getEnseignant()->getId(), 'etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }
}
