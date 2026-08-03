<?php

namespace App\Entity;

use App\Repository\ParticipationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParticipationRepository::class)]
class Participation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Liste::class, inversedBy: 'participations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Liste $adherent = null;

    #[ORM\ManyToOne(targetEntity: Evenement::class, inversedBy: 'participations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Evenement $evenement = null;

    #[ORM\Column(length: 20)]
    private string $statutPaiement = 'impaye';

    #[ORM\Column]
    private ?int $quantite = 1;

    #[ORM\Column(nullable: true)]
    private ?float $montantTotal = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $datePaiement = null;

    /**
     * @var Collection<int, PaiementEvenement>
     */
    #[ORM\OneToMany(mappedBy: 'participation', targetEntity: PaiementEvenement::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $paiements;

    public function __construct()
    {
        $this->paiements = new ArrayCollection();
        $this->statutPaiement = 'impaye';
        $this->quantite = 1;
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

    public function getEvenement(): ?Evenement
    {
        return $this->evenement;
    }

    public function setEvenement(?Evenement $evenement): static
    {
        $this->evenement = $evenement;
        $this->calculerMontantTotal();
        return $this;
    }

    public function getStatutPaiement(): string
    {
        return $this->statutPaiement;
    }

    public function setStatutPaiement(string $statutPaiement): static
    {
        $this->statutPaiement = $statutPaiement;
        return $this;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = $quantite;
        $this->calculerMontantTotal();
        return $this;
    }

    public function getMontantTotal(): ?float
    {
        return $this->montantTotal;
    }

    public function setMontantTotal(?float $montantTotal): static
    {
        $this->montantTotal = $montantTotal;
        return $this;
    }

    public function calculerMontantTotal(): void
    {
        if ($this->evenement && $this->evenement->getMontant()) {
            $this->montantTotal = $this->evenement->getMontant() * $this->quantite;
        } else {
            $this->montantTotal = 0;
        }
    }

    public function isPaye(): bool
    {
        return $this->statutPaiement === 'paye';
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

    public function getDatePaiement(): ?\DateTime
    {
        return $this->datePaiement;
    }

    public function setDatePaiement(?\DateTime $datePaiement): static
    {
        $this->datePaiement = $datePaiement;
        return $this;
    }

    /**
     * @return Collection<int, PaiementEvenement>
     */
    public function getPaiements(): Collection
    {
        return $this->paiements;
    }

    public function addPaiement(PaiementEvenement $paiement): static
    {
        if (!$this->paiements->contains($paiement)) {
            $this->paiements->add($paiement);
            $paiement->setParticipation($this);
            $this->updateFromPaiements();
        }
        return $this;
    }

    public function removePaiement(PaiementEvenement $paiement): static
    {
        if ($this->paiements->removeElement($paiement)) {
            if ($paiement->getParticipation() === $this) {
                $paiement->setParticipation(null);
            }
            $this->updateFromPaiements();
        }
        return $this;
    }

    /**
     * Met à jour les champs de la participation à partir des paiements
     */
    private function updateFromPaiements(): void
    {
        if ($this->paiements->isEmpty()) {
            $this->statutPaiement = 'impaye';
            $this->montantTotal = $this->evenement ? $this->evenement->getMontant() * $this->quantite : 0;
            $this->datePaiement = null;
            $this->reference = null;
            return;
        }

        $totalPaye = 0;
        $dernierPaiement = null;

        foreach ($this->paiements as $paiement) {
            $totalPaye += $paiement->getMontant();
            if (!$dernierPaiement || $paiement->getDatePaiement() > $dernierPaiement->getDatePaiement()) {
                $dernierPaiement = $paiement;
            }
        }

        $montantTotal = $this->evenement ? $this->evenement->getMontant() * $this->quantite : 0;

        if ($totalPaye >= $montantTotal) {
            $this->statutPaiement = 'paye';
            $this->montantTotal = $montantTotal;
        } elseif ($totalPaye > 0) {
            $this->statutPaiement = 'partiel';
            $this->montantTotal = $totalPaye;
        } else {
            $this->statutPaiement = 'impaye';
            $this->montantTotal = $montantTotal;
        }

        if ($dernierPaiement) {
            $this->datePaiement = $dernierPaiement->getDatePaiement();
            $this->reference = $dernierPaiement->getReference();
        }
    }

    /**
     * Récupère le montant total payé
     */
    public function getMontantTotalPaye(): float
    {
        $total = 0;
        foreach ($this->paiements as $paiement) {
            $total += $paiement->getMontant();
        }
        return $total;
    }

    /**
     * Récupère le reste à payer
     */
    public function getResteAPayer(): float
    {
        $montantTotal = $this->evenement ? $this->evenement->getMontant() * $this->quantite : 0;
        return max(0, $montantTotal - $this->getMontantTotalPaye());
    }
}