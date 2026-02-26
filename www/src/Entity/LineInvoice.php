<?php

namespace App\Entity;

use App\Repository\LineInvoiceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LineInvoiceRepository::class)]
class LineInvoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private ?string $LineProduct = null;

    #[ORM\Column]
    private ?int $LinePrice = null;

    #[ORM\ManyToOne(inversedBy: 'lineInvoices')]
    private ?Invoice $invoice = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLineProduct(): ?string
    {
        return $this->LineProduct;
    }

    public function setLineProduct(string $LineProduct): static
    {
        $this->LineProduct = $LineProduct;

        return $this;
    }

    public function getLinePrice(): ?int
    {
        return $this->LinePrice;
    }

    public function setLinePrice(int $LinePrice): static
    {
        $this->LinePrice = $LinePrice;

        return $this;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): static
    {
        $this->invoice = $invoice;

        return $this;
    }
}
