<?php

namespace App\Entity;

use App\Repository\CotisationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CotisationRepository::class)]
class Cotisation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'cotisations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Liste $adherent = null;

    #[ORM\Column]
    private ?float $montant = null;

    #[ORM\Column]
    private ?float $montantPaye = null;

    #[ORM\Column(length: 10)]
    private ?string $periode = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $datePaiement = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $modePaiement = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = 'impaye';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $observation = null;

    /**
     * @var Collection<int, Paiement>
     */
    #[ORM\OneToMany(mappedBy: 'cotisation', targetEntity: Paiement::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $paiements;

    public function __construct()
    {
        $this->montantPaye = 0;
        $this->statut = 'impaye';
        $this->paiements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAdherent(): ?Liste
    {
        return $this->adherent;
    }

    public function setAdherent(?Liste $adherent): static
    {
        $this->adherent = $adherent;
        return $this;
    }

    public function getMontant(): ?float
    {
        return $this->montant;
    }

    public function setMontant(float $montant): static
    {
        $this->montant = $montant;
        return $this;
    }

    public function getMontantPaye(): ?float
    {
        return $this->montantPaye;
    }

    public function setMontantPaye(float $montantPaye): static
    {
        $this->montantPaye = $montantPaye;
        $this->updateStatut();
        return $this;
    }

    public function getPeriode(): ?string
    {
        return $this->periode;
    }

    public function setPeriode(string $periode): static
    {
        $this->periode = $periode;
        return $this;
    }

    public function getDatePaiement(): ?\DateTimeInterface
    {
        return $this->datePaiement;
    }

    public function setDatePaiement(?\DateTimeInterface $datePaiement): static
    {
        $this->datePaiement = $datePaiement;
        return $this;
    }

    public function getModePaiement(): ?string
    {
        return $this->modePaiement;
    }

    public function setModePaiement(?string $modePaiement): static
    {
        $this->modePaiement = $modePaiement;
        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }
    
    public function getObservation(): ?string
    {
        return $this->observation;
    }

    public function setObservation(?string $observation): static
    {
        $this->observation = $observation;
        return $this;
    }

    public function updateStatut(): void
    {
        if ($this->montantPaye >= $this->montant) {
            $this->statut = 'paye';
        } elseif ($this->montantPaye > 0) {
            $this->statut = 'partiel';
        } else {
            $this->statut = 'impaye';
        }
    }

    public function isPaye(): bool
    {
        return $this->statut === 'paye';
    }

    public function getResteAPayer(): float
    {
        return max(0, $this->montant - $this->montantPaye);
    }

    /**
     * @return Collection<int, Paiement>
     */
    public function getPaiements(): Collection
    {
        return $this->paiements;
    }

    public function addPaiement(Paiement $paiement): static
    {
        if (!$this->paiements->contains($paiement)) {
            $this->paiements->add($paiement);
            $paiement->setCotisation($this);
            $this->montantPaye += $paiement->getMontant();
            $this->updateStatut();
        }
        return $this;
    }

    public function removePaiement(Paiement $paiement): static
    {
        if ($this->paiements->removeElement($paiement)) {
            if ($paiement->getCotisation() === $this) {
                $paiement->setCotisation(null);
            }
            $this->montantPaye -= $paiement->getMontant();
            $this->updateStatut();
        }
        return $this;
    }
}