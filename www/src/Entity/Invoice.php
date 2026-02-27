<?php

namespace App\Entity;

use App\Repository\InvoiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private ?string $person = null;

    #[ORM\Column(length: 255)]
    private ?string $path = null;

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    /**
     * @var Collection<int, LineInvoice>
     */
    #[ORM\OneToMany(targetEntity: LineInvoice::class, mappedBy: 'invoice')]
    private Collection $lineInvoices;

    public function __construct()
    {
        $this->lineInvoices = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getPerson(): ?string
    {
        return $this->person;
    }

    public function setPerson(string $person): static
    {
        $this->person = $person;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection<int, LineInvoice>
     */
    public function getLineInvoices(): Collection
    {
        return $this->lineInvoices;
    }

    public function addLineInvoice(LineInvoice $lineInvoice): static
    {
        if (!$this->lineInvoices->contains($lineInvoice)) {
            $this->lineInvoices->add($lineInvoice);
            $lineInvoice->setInvoice($this);
        }

        return $this;
    }

    public function removeLineInvoice(LineInvoice $lineInvoice): static
    {
        if ($this->lineInvoices->removeElement($lineInvoice)) {
            // set the owning side to null (unless already changed)
            if ($lineInvoice->getInvoice() === $this) {
                $lineInvoice->setInvoice(null);
            }
        }

        return $this;
    }
}
