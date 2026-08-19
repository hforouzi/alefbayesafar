<?php


namespace App\Service;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\Environment;

class DataTableExtension extends AbstractExtension
{
    private Environment $twig;
    
    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }
    
    public function getFunctions(): array
    {
        return [
            new TwigFunction('datatable',
                [$this, 'renderDataTable'],
                ['is_safe' => ['html']]),
        ];
    }
    
    public function renderDataTable(
        string $id,
        array $columns,
        array $data,
        array $actions,
        string $listName = ''
    ): string {
        return $this->twig->render('components/datatable.html.twig',
            [
                'table_id' => $id,
                'columns' => $columns,
                'data' => $data,
                'actions' => $actions,
                  'listName' => $listName,
            ]);
    }
}
