<?php

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/categories', name: 'app_category_')]
class CategoryController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository): Response
    {
        return $this->render('category/index.html.twig', [
            'categories' => $categoryRepository->findAllWithTaskCounts(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'], priority: 1)]
    public function new(Request $request): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($category);
            $this->entityManager->flush();

            $this->addFlash('success', 'Category created.');

            return $this->redirectToRoute('app_category_index');
        }

        return $this->render('category/form.html.twig', [
            'form' => $form,
            'category' => $category,
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Category $category): Response
    {
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Category updated.');

            return $this->redirectToRoute('app_category_index');
        }

        return $this->render('category/form.html.twig', [
            'form' => $form,
            'category' => $category,
        ]);
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Category $category): Response
    {
        $submittedToken = (string) $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('delete'.$category->getId(), $submittedToken)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        // Tasks of this category keep existing: the FK is onDelete="SET NULL".
        $this->entityManager->remove($category);
        $this->entityManager->flush();

        $this->addFlash('success', 'Category deleted.');

        return $this->redirectToRoute('app_category_index');
    }
}
