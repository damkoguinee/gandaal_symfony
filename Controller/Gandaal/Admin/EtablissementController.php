<?php

namespace App\Controller\Gandaal\Admin;

use App\Entity\Etablissement;
use App\Form\EtablissementType;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\EtablissementRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/admin/etablissement')]
class EtablissementController extends AbstractController
{
    #[Route('/', name: 'app_gandaal_admin_etablissement_index', methods: ['GET'])]
    public function index(EtablissementRepository $etablissementRepository): Response
    {
        return $this->render('gandaal/admin/etablissement/index.html.twig', [
            'etablissements' => $etablissementRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_gandaal_admin_etablissement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $etablissement = new Etablissement();
        $form = $this->createForm(EtablissementType::class, $etablissement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $image = $form->get("image")->getData();
            if ($image) {
                $nomFichier= pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
                $slugger = new AsciiSlugger();
                $nouveauNomFichier = $slugger->slug($nomFichier);
                $nouveauNomFichier .="_".uniqid();
                $nouveauNomFichier .= "." .$image->guessExtension();
                $image->move($this->getParameter("dossier_images_config"),$nouveauNomFichier);
                $etablissement->setImage($nouveauNomFichier);
            }
            $entityManager->persist($etablissement);
            $entityManager->flush();
            $this->addFlash("success", "Etablissement enregistré avec succès :)");
            return $this->redirectToRoute('app_gandaal_admin_etablissement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin/etablissement/new.html.twig', [
            'etablissement' => $etablissement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_admin_etablissement_show', methods: ['GET'])]
    public function show(Etablissement $etablissement): Response
    {
        return $this->render('gandaal/admin/etablissement/show.html.twig', [
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gandaal_admin_etablissement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Etablissement $etablissement, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(EtablissementType::class, $etablissement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $image =$form->get("image")->getData();
            if ($image) {
                if ($etablissement->getImage()) {
                    $ancienLogo=$this->getParameter("dossier_images_config")."/".$etablissement->getImage();
                    if (file_exists($ancienLogo)) {
                        unlink($ancienLogo);
                    }
                }
                $nomLogo= pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
                $slugger = new AsciiSlugger();
                $nouveauNomLogo = $slugger->slug($nomLogo);
                $nouveauNomLogo .="_".uniqid();
                $nouveauNomLogo .= "." .$image->guessExtension();
                $image->move($this->getParameter("dossier_images_config"),$nouveauNomLogo);
                $etablissement->setImage($nouveauNomLogo);
            }
            $entityManager->flush();
            $this->addFlash("success", "Etablissement modifié avec succès :)");
            return $this->redirectToRoute('app_gandaal_admin_etablissement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin/etablissement/edit.html.twig', [
            'etablissement' => $etablissement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_admin_etablissement_delete', methods: ['POST'])]
    public function delete(Request $request, Etablissement $etablissement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$etablissement->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($etablissement);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_admin_etablissement_index', [], Response::HTTP_SEE_OTHER);
    }
}
