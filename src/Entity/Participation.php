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

    #[ORM\Column(nullable: true)]
    private ?float $montantTotal = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $datePaiement = null;

    #[ORM\Column(nullable: true)]
    private ?int $nbParticipants = null;

    /**
     * @var Collection<int, ParticipationLigne>
     */
    #[ORM\OneToMany(mappedBy: 'participation', targetEntity: ParticipationLigne::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $participationLignes;

    /**
     * @var Collection<int, PaiementEvenement>
     */
    #[ORM\OneToMany(mappedBy: 'participation', targetEntity: PaiementEvenement::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $paiements;

    public function __construct()
    {
        $this->participationLignes = new ArrayCollection();
        $this->paiements = new ArrayCollection();
        $this->statutPaiement = 'impaye';
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

    public function getMontantTotal(): ?float
    {
        return $this->montantTotal;
    }

    public function setMontantTotal(?float $montantTotal): static
    {
        $this->montantTotal = $montantTotal;
        return $this;
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

     public function getNbParticipants(): ?int
    {
        return $this->nbParticipants;
    }

    public function setNbParticipants(?int $nbParticipants): static
    {
        $this->nbParticipants = $nbParticipants;
        return $this;
    }

    /**
     * @return Collection<int, ParticipationLigne>
     */
    public function getParticipationLignes(): Collection
    {
        return $this->participationLignes;
    }

    public function addParticipationLigne(ParticipationLigne $participationLigne): static
    {
        if (!$this->participationLignes->contains($participationLigne)) {
            $this->participationLignes->add($participationLigne);
            $participationLigne->setParticipation($this);
            $this->recalculerMontantTotal();
        }
        return $this;
    }

    public function removeParticipationLigne(ParticipationLigne $participationLigne): static
    {
        if ($this->participationLignes->removeElement($participationLigne)) {
            if ($participationLigne->getParticipation() === $this) {
                $participationLigne->setParticipation(null);
            }
            $this->recalculerMontantTotal();
        }
        return $this;
    }

    /**
     * Recalcule le montant total à partir des lignes de participation
     */
    public function recalculerMontantTotal(): void
    {
        $total = 0;
        foreach ($this->participationLignes as $pl) {
            $total += $pl->getMontantLigne();
        }
        $this->montantTotal = $total;
    }

    /**
     * Retourne la quantité totale de participants (somme des quantités de toutes les lignes)
     * Cette méthode est utilisée dans les templates
     */
    public function getQuantiteTotale(): int
    {
        // Si l'événement a des désignations, on somme les quantités des lignes
        if ($this->participationLignes->count() > 0) {
            $total = 0;
            foreach ($this->participationLignes as $pl) {
                $total += $pl->getQuantite();
            }
            return $total;
        }
        
        // Sinon, retourner nbParticipants
        return $this->nbParticipants ?? 1;
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

    private function updateFromPaiements(): void
    {
        if ($this->paiements->isEmpty()) {
            $this->statutPaiement = 'impaye';
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

        $montantTotal = $this->montantTotal ?? 0;

        if ($totalPaye >= $montantTotal && $montantTotal > 0) {
            $this->statutPaiement = 'paye';
        } elseif ($totalPaye > 0) {
            $this->statutPaiement = 'partiel';
        } else {
            $this->statutPaiement = 'impaye';
        }

        if ($dernierPaiement) {
            $this->datePaiement = $dernierPaiement->getDatePaiement();
            $this->reference = $dernierPaiement->getReference();
        }
    }

    public function getMontantTotalPaye(): float
    {
        $total = 0;
        foreach ($this->paiements as $paiement) {
            $total += $paiement->getMontant();
        }
        return $total;
    }

    public function getResteAPayer(): float
    {
        return max(0, ($this->montantTotal ?? 0) - $this->getMontantTotalPaye());
    }

    public function setQuantite(int $quantite): static
    {
        return $this;
    }
}