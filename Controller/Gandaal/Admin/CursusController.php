<?php

namespace App\Controller\Gandaal\Admin;

use App\Entity\Cursus;
use App\Form\CursusType;
use App\Repository\CursusRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/admin/cursus')]
class CursusController extends AbstractController
{
    #[Route('/', name: 'app_gandaal_admin_cursus_index', methods: ['GET'])]
    public function index(CursusRepository $cursusRep): Response
    {
        return $this->render('gandaal/admin/cursus/index.html.twig', [
            'cursuss' => $cursusRep->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_gandaal_admin_cursus_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $cursus = new Cursus();
        $form = $this->createForm(CursusType::class, $cursus);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            $entityManager->persist($cursus);
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_admin_cursus_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin/cursus/new.html.twig', [
            'cursus' => $cursus,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_admin_cursus_show', methods: ['GET'])]
    public function show(Cursus $cursus): Response
    {
        return $this->render('gandaal/admin/cursus/show.html.twig', [
            'cursus' => $cursus,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gandaal_admin_cursus_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Cursus $cursus, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CursusType::class, $cursus);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_admin_cursus_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin/cursus/edit.html.twig', [
            'cursus' => $cursus,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_admin_cursus_delete', methods: ['POST'])]
    public function delete(Request $request, Cursus $cursus, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$cursus->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($cursus);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_admin_cursus_index', [], Response::HTTP_SEE_OTHER);
    }
}
