<?php

namespace App\Controller\Gandaal\Admin;

use App\Entity\Entreprise;
use App\Form\EntrepriseType;
use App\Repository\EntrepriseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/admin/entreprise')]
class EntrepriseController extends AbstractController
{
    #[Route('/', name: 'app_gandaal_admin_entreprise_index', methods: ['GET'])]
    public function index(EntrepriseRepository $entrepriseRepository): Response
    {
        return $this->render('gandaal/admin/entreprise/index.html.twig', [
            'entreprises' => $entrepriseRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_gandaal_admin_entreprise_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $entreprise = new Entreprise();
        $form = $this->createForm(EntrepriseType::class, $entreprise);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $logo = $form->get("logo")->getData();
            if ($logo) {
                $nomFichier= pathinfo($logo->getClientOriginalName(), PATHINFO_FILENAME);
                $slugger = new AsciiSlugger();
                $nouveauNomFichier = $slugger->slug($nomFichier);
                $nouveauNomFichier .="_".uniqid();
                $nouveauNomFichier .= "." .$logo->guessExtension();
                $logo->move($this->getParameter("dossier_images_config"),$nouveauNomFichier);
                $entreprise->setLogo($nouveauNomFichier);
            }
            $entityManager->persist($entreprise);
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_admin_entreprise_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin/entreprise/new.html.twig', [
            'entreprise' => $entreprise,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_admin_entreprise_show', methods: ['GET'])]
    public function show(Entreprise $entreprise): Response
    {
        return $this->render('gandaal/admin/entreprise/show.html.twig', [
            'entreprise' => $entreprise,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gandaal_admin_entreprise_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Entreprise $entreprise, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(EntrepriseType::class, $entreprise);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $logo =$form->get("logo")->getData();
            if ($logo) {
                if ($entreprise->getLogo()) {
                    $ancienLogo=$this->getParameter("dossier_images_config")."/".$entreprise->getLogo();
                    if (file_exists($ancienLogo)) {
                        unlink($ancienLogo);
                    }
                }
                $nomLogo= pathinfo($logo->getClientOriginalName(), PATHINFO_FILENAME);
                $slugger = new AsciiSlugger();
                $nouveauNomLogo = $slugger->slug($nomLogo);
                $nouveauNomLogo .="_".uniqid();
                $nouveauNomLogo .= "." .$logo->guessExtension();
                $logo->move($this->getParameter("dossier_images_config"),$nouveauNomLogo);
                $entreprise->setLogo($nouveauNomLogo);
            }
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_admin_entreprise_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin/entreprise/edit.html.twig', [
            'entreprise' => $entreprise,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_admin_entreprise_delete', methods: ['POST'])]
    public function delete(Request $request, Entreprise $entreprise, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$entreprise->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($entreprise);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_admin_entreprise_index', [], Response::HTTP_SEE_OTHER);
    }
}
