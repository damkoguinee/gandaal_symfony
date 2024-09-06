<?php

namespace App\Controller\Gandaal\AdminSite;

use App\Entity\Cursus;
use App\Entity\Etablissement;
use App\Form\CursusType;
use App\Repository\CursusRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/admin/site/cursus')]
class CursusController extends AbstractController
{
    #[Route('/index/{etablissement}', name: 'app_gandaal_admin_site_cursus_index', methods: ['GET'])]
    public function index(CursusRepository $cursusRep, Etablissement $etablissement): Response
    {
        $cursus = $cursusRep->findBy(['etablissement' => $etablissement]);
        return $this->render('gandaal/admin_site/cursus/index.html.twig', [
            'cursuss' => $cursus,
            'etablissement' => $etablissement
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_admin_site_cursus_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Etablissement $etablissement, EntityManagerInterface $entityManager): Response
    {
        $cursus = new Cursus();
        $form = $this->createForm(CursusType::class, $cursus);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $cursus->setEtablissement($etablissement);
            $entityManager->persist($cursus);
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_admin_site_cursus_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin_site/cursus/new.html.twig', [
            'cursus' => $cursus,
            'form' => $form,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/show/{id}/{etablissement}', name: 'app_gandaal_admin_site_cursus_show', methods: ['GET'])]
    public function show(Cursus $cursus, Etablissement $etablissement): Response
    {
        return $this->render('gandaal/admin_site/cursus/show.html.twig', [
            'cursus' => $cursus,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/edit/{id}/{etablissement}', name: 'app_gandaal_admin_site_cursus_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Cursus $cursus, Etablissement $etablissement, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CursusType::class, $cursus);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $cursus->setEtablissement($etablissement);
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_admin_site_cursus_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin_site/cursus/edit.html.twig', [
            'cursus' => $cursus,
            'form' => $form,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/{id}/{etablissement}', name: 'app_gandaal_admin_site_cursus_delete', methods: ['POST'])]
    public function delete(Request $request, Cursus $cursus, Etablissement $etablissement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$cursus->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($cursus);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_admin_site_cursus_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }
}
