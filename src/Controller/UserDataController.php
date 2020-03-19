<?php

namespace App\Controller;

use App\Entity\UserData;
use App\Form\UserDataType;
use App\Repository\UserDataRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/user/data")
 */
class UserDataController extends AbstractController
{
    /**
     * @Route("/", name="user_data_index", methods={"GET"})
     */
    public function index(UserDataRepository $userDataRepository): Response
    {
        return $this->render('user_data/index.html.twig', [
            'user_datas' => $userDataRepository->findAll(),
        ]);
    }

    /**
     * @Route("/new", name="user_data_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $userDatum = new UserData();
        $form = $this->createForm(UserDataType::class, $userDatum);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($userDatum);
            $entityManager->flush();

            return $this->redirectToRoute('user_data_index');
        }

        return $this->render('user_data/new.html.twig', [
            'user_datum' => $userDatum,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="user_data_show", methods={"GET"})
     */
    public function show(UserData $userDatum): Response
    {
        return $this->render('user_data/show.html.twig', [
            'user_datum' => $userDatum,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="user_data_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, UserData $userDatum): Response
    {
        $form = $this->createForm(UserDataType::class, $userDatum);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('user_data_index', [
                'id' => $userDatum->getId(),
            ]);
        }

        return $this->render('user_data/edit.html.twig', [
            'user_datum' => $userDatum,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="user_data_delete", methods={"DELETE"})
     */
    public function delete(Request $request, UserData $userDatum): Response
    {
        if ($this->isCsrfTokenValid('delete'.$userDatum->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($userDatum);
            $entityManager->flush();
        }

        return $this->redirectToRoute('user_data_index');
    }
}
