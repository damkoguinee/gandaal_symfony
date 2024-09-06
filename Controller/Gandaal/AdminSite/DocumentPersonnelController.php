<?php

namespace App\Controller\Gandaal\AdminSite;

use App\Entity\Personnel;
use App\Entity\Etablissement;
use App\Entity\DocumentPersonnel;
use App\Form\DocumentPersonnelType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\DocumentPersonnelRepository;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/admin/site/document/personnel')]
class DocumentPersonnelController extends AbstractController
{
    #[Route('/', name: 'app_gandaal_admin_site_document_personnel_index', methods: ['GET'])]
    public function index(DocumentPersonnelRepository $documentPersonnelRepository): Response
    {
        return $this->render('gandaal/admin_site/document_personnel/index.html.twig', [
            'document_personnels' => $documentPersonnelRepository->findAll(),
        ]);
    }

    #[Route('/new/{personnel}/{etablissement}', name: 'app_gandaal_admin_site_document_personnel_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Personnel $personnel, Etablissement $etablissement, SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        $documentPersonnel = new DocumentPersonnel();
        $form = $this->createForm(DocumentPersonnelType::class, $documentPersonnel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fichier_document = $form->get("nom")->getData();
            if ($fichier_document) {
                $nomFichier= pathinfo($fichier_document->getClientOriginalName(), PATHINFO_FILENAME);
                $slugger = new AsciiSlugger();
                $nouveauNomFichier = $slugger->slug($nomFichier);
                $nouveauNomFichier .="_".uniqid();
                $nouveauNomFichier .= "." .$fichier_document->guessExtension();
                $fichier_document->move($this->getParameter("dossier_personnels"), $nouveauNomFichier);
                $documentPersonnel->setNom($nouveauNomFichier)
                        ->setPersonnel($personnel);
                $entityManager->persist($documentPersonnel);

            }            
            $entityManager->persist($documentPersonnel);
            $entityManager->flush();

            $this->addFlash("success", "Document ajouté avec succès :)");
            return $this->redirectToRoute('app_gandaal_admin_site_personnel_show', ['id' => $personnel->getId(), 'etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin_site/document_personnel/new.html.twig', [
            'document_eleve' => $documentPersonnel,
            'form' => $form,
            'etablissement' => $etablissement,
            'personnel' => $personnel,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_admin_site_document_personnel_show', methods: ['GET'])]
    public function show(DocumentPersonnel $documentPersonnel): Response
    {
        return $this->render('gandaal/admin_site/document_personnel/show.html.twig', [
            'document_personnel' => $documentPersonnel,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gandaal_admin_site_document_personnel_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, DocumentPersonnel $documentPersonnel, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DocumentPersonnelType::class, $documentPersonnel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_admin_site_document_personnel_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin_site/document_personnel/edit.html.twig', [
            'document_personnel' => $documentPersonnel,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/{etablissement}', name: 'app_gandaal_admin_site_document_personnel_delete', methods: ['POST'])]
    public function delete(Request $request, DocumentPersonnel $documentPersonnel, Etablissement $etablissement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$documentPersonnel->getId(), $request->getPayload()->getString('_token'))) {
            if ($documentPersonnel->getNom()) {
                $ancienFichier=$this->getParameter("dossier_personnels")."/".$documentPersonnel->getNom();
                if (file_exists($ancienFichier)) {
                    unlink($ancienFichier);
                }
            }
            $entityManager->remove($documentPersonnel);
            $entityManager->flush();
        }
        $this->addFlash("success", "Document supprimé avec succès :)");
        return $this->redirectToRoute('app_gandaal_admin_site_personnel_show', ['id' => $documentPersonnel->getPersonnel()->getId(), 'etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }
}
