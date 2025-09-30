<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use Symfony\Component\HttpFoundation\Request;

final class CategoryController extends AbstractController
{
    #[Route('/admin/category/{id}/delete', name: 'app_category_delete')]
    public function deleteCategory(Category $category, entityManagerInterface $entityManager, Request $request):Response
    {
        $entityManager->remove($category);
        $entityManager->flush();
        $this->addFlash('success','La catégorie a été supprimée');
        return $this->redirectToRoute('app_category');
    }


    #[Route('/admin/category', name: 'app_category')]
    public function index(CategoryRepository $categoryRepository): Response
    {
        $categories = $categoryRepository->findAll();
        return $this->render('category/index.html.twig', [
            'categories' => $categories
        ]);

    }

    #[Route('/admin/category/new', name: 'app_category_new')]
    public function addCategory(EntityManagerInterface $entityManager, Request $request): Response
    {

        
        $category = new Category();

        $form= $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){
            $entityManager->persist($category);
            $entityManager->flush();

            $this->addFlash('success','La catégorie a été ajoutée');
            return $this->redirectToRoute('app_category');
        }
        return $this->render('category/new.html.twig',['categoryForm'=>
        $form->createView()]);
    }


        #[Route('/admin/category/{id}/update', name: 'app_category_update')]
        public function updateCategory(Category $category, EntityManagerInterface $entityManager,
        Request $request):Response
        {
             $form = $this->createForm(CategoryType::class, $category);
             $form->handleRequest($request);

             if($form->isSubmitted() && $form->isValid()){
                $entityManager->flush();

                $this->addFlash('success','La catégorie a été modifiée');
                return $this->redirectToRoute('app_category');
             }

             return $this->render('category/update.html.twig',['categoryForm'=>
             $form->createView()]);
        }

    
}
