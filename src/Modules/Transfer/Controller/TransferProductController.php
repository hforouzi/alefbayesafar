<?php

namespace App\Modules\Transfer\Controller;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Transfer\Entity\TransferProduct;
use App\Modules\Transfer\Form\TransferProductType;
use App\Modules\Transfer\Repository\TransferProductRepository;
use App\Modules\Transfer\Service\TransferAdminListRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/transfer-commerce/products')]
class TransferProductController extends AbstractController
{
    #[Route('/', name: 'transfer_product_index', methods: ['GET'])]
    public function index(Request $request, TransferProductRepository $productRepository, TransferAdminListRequest $adminListRequest): Response
    {
        $filters = $adminListRequest->productFilters($request);
        $products = $productRepository->findForAdminPage($filters);

        return $this->render('@Transfer/product/index.html.twig', [
            'products' => $products->items,
            'pagination' => $products,
        ]);
    }

    #[Route('/new', name: 'transfer_product_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        $product = new TransferProduct();
        $form = $this->createForm(TransferProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->applyUnmappedFields($form, $product, $entityManager);
            $this->validateProduct($form, $product, $validator);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($product);
            $entityManager->flush();

            $this->addFlash('success', 'transfer.product.flash.created');

            return $this->redirectToRoute('transfer_product_index');
        }

        return $this->render('@Transfer/product/new.html.twig', [
            'product' => $product,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'transfer_product_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, TransferProduct $product, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        $form = $this->createForm(TransferProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->applyUnmappedFields($form, $product, $entityManager);
            $this->validateProduct($form, $product, $validator);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'transfer.product.flash.updated');

            return $this->redirectToRoute('transfer_product_index');
        }

        return $this->render('@Transfer/product/edit.html.twig', [
            'product' => $product,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/toggle', name: 'transfer_product_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(Request $request, TransferProduct $product, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle_transfer_product_' . $product->getId(), (string) $request->request->get('_token'))) {
            $product->setActive(!$product->isActive());
            $entityManager->flush();
        }

        return $this->redirectToRoute('transfer_product_index');
    }

    private function applyUnmappedFields(FormInterface $form, TransferProduct $product, EntityManagerInterface $entityManager): void
    {
        $product->setOriginAirport($this->optionalEntity($form, 'originAirportId', Airport::class, $entityManager));
        $product->setOriginCity($this->optionalEntity($form, 'originCityId', City::class, $entityManager));
        $product->setOriginHotel($this->optionalEntity($form, 'originHotelId', Hotel::class, $entityManager));
        $product->setDestinationAirport($this->optionalEntity($form, 'destinationAirportId', Airport::class, $entityManager));
        $product->setDestinationCity($this->optionalEntity($form, 'destinationCityId', City::class, $entityManager));
        $product->setDestinationHotel($this->optionalEntity($form, 'destinationHotelId', Hotel::class, $entityManager));

        $originCount = \count(array_filter([$product->getOriginAirport(), $product->getOriginCity(), $product->getOriginHotel()], static fn (mixed $v): bool => $v !== null));
        if ($originCount !== 1) {
            $form->get('originAirportId')->addError(new FormError('transfer.product.validation.origin_exactly_one'));
        }

        $destinationCount = \count(array_filter([$product->getDestinationAirport(), $product->getDestinationCity(), $product->getDestinationHotel()], static fn (mixed $v): bool => $v !== null));
        if ($destinationCount !== 1) {
            $form->get('destinationAirportId')->addError(new FormError('transfer.product.validation.destination_exactly_one'));
        }
    }

    /**
     * @param class-string $class
     */
    private function optionalEntity(FormInterface $form, string $field, string $class, EntityManagerInterface $entityManager): ?object
    {
        $value = (string) $form->get($field)->getData();
        if ($value === '') {
            return null;
        }

        if (!ctype_digit($value)) {
            $form->get($field)->addError(new FormError('transfer.product.validation.invalid_reference'));

            return null;
        }

        $entity = $entityManager->getRepository($class)->find((int) $value);
        if (!\is_object($entity)) {
            $form->get($field)->addError(new FormError('transfer.product.validation.invalid_reference'));
        }

        return \is_object($entity) ? $entity : null;
    }

    private function validateProduct(FormInterface $form, TransferProduct $product, ValidatorInterface $validator): void
    {
        foreach ($validator->validate($product) as $violation) {
            $field = $this->formFieldForViolationPath($violation->getPropertyPath());
            if ($form->has($field)) {
                $form->get($field)->addError(new FormError((string) $violation->getMessage()));

                continue;
            }

            $form->addError(new FormError((string) $violation->getMessage()));
        }
    }

    private function formFieldForViolationPath(string $propertyPath): string
    {
        return match ($propertyPath) {
            'originAirport' => 'originAirportId',
            'destinationAirport' => 'destinationAirportId',
            default => $propertyPath,
        };
    }
}
