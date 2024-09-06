<?php

namespace App\Controller\Gandaal\Admin;

use App\Entity\ConfigFonction;
use App\Entity\Etablissement;
use App\Form\ConfigFonctionType;
use App\Repository\ConfigFonctionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gandaal/admin/config/fonction')]
class ConfigFonctionController extends AbstractController
{
    #[Route('/', name: 'app_gandaal_admin_config_fonction_index', methods: ['GET'])]
    public function index(ConfigFonctionRepository $configFonctionRepository): Response
    {
        return $this->render('gandaal/admin/config_fonction/index.html.twig', [
            'config_fonctions' => $configFonctionRepository->findAll(),
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_admin_config_fonction_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Etablissement $etablissement, EntityManagerInterface $entityManager): Response
    {
        $configFonction = new ConfigFonction();
        $form = $this->createForm(ConfigFonctionType::class, $configFonction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($configFonction);
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_admin_site_personnel_new', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin/config_fonction/new.html.twig', [
            'config_fonction' => $configFonction,
            'form' => $form,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_admin_config_fonction_show', methods: ['GET'])]
    public function show(ConfigFonction $configFonction): Response
    {
        return $this->render('gandaal/admin/config_fonction/show.html.twig', [
            'config_fonction' => $configFonction,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gandaal_admin_config_fonction_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ConfigFonction $configFonction, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ConfigFonctionType::class, $configFonction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_admin_config_fonction_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin/config_fonction/edit.html.twig', [
            'config_fonction' => $configFonction,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_admin_config_fonction_delete', methods: ['POST'])]
    public function delete(Request $request, ConfigFonction $configFonction, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$configFonction->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($configFonction);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_admin_config_fonction_index', [], Response::HTTP_SEE_OTHER);
    }
}
