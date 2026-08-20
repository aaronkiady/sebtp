<?php

namespace App\Entity;

use App\Repository\EvenementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EvenementRepository::class)]
class Evenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $periode = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(length: 20)]
    private ?string $typeDocument = 'note_debit';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $statut = 'actif';

    // NOUVEAU : Montant fixe (optionnel)
    #[ORM\Column(nullable: true)]
    private ?float $montantFixe = null;

    /**
     * @var Collection<int, LigneEvenement>
     */
    #[ORM\OneToMany(mappedBy: 'evenement', targetEntity: LigneEvenement::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lignes;

    /**
     * @var Collection<int, Participation>
     */
    #[ORM\OneToMany(mappedBy: 'evenement', targetEntity: Participation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $participations;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
        $this->participations = new ArrayCollection();
        $this->statut = 'actif';
        $this->typeDocument = 'note_debit';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPeriode(): ?string
    {
        return $this->periode;
    }

    public function setPeriode(?string $periode): static
    {
        $this->periode = $periode;
        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getTypeDocument(): ?string
    {
        return $this->typeDocument;
    }

    public function setTypeDocument(string $typeDocument): static
    {
        $this->typeDocument = $typeDocument;
        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;
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

    // NOUVEAU : Getter et Setter pour montantFixe
    public function getMontantFixe(): ?float
    {
        return $this->montantFixe;
    }

    public function setMontantFixe(?float $montantFixe): static
    {
        $this->montantFixe = $montantFixe;
        return $this;
    }

    /**
     * @return Collection<int, LigneEvenement>
     */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    public function addLigne(LigneEvenement $ligne): static
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setEvenement($this);
        }
        return $this;
    }

    public function removeLigne(LigneEvenement $ligne): static
    {
        if ($this->lignes->removeElement($ligne)) {
            if ($ligne->getEvenement() === $this) {
                $ligne->setEvenement(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Participation>
     */
    public function getParticipations(): Collection
    {
        return $this->participations;
    }

    public function addParticipation(Participation $participation): static
    {
        if (!$this->participations->contains($participation)) {
            $this->participations->add($participation);
            $participation->setEvenement($this);
        }
        return $this;
    }

    public function removeParticipation(Participation $participation): static
    {
        if ($this->participations->removeElement($participation)) {
            if ($participation->getEvenement() === $this) {
                $participation->setEvenement(null);
            }
        }
        return $this;
    }

    public function getTotalParticipants(): int
    {
        return $this->participations->count();
    }

    public function getTotalPaye(): int
    {
        $count = 0;
        foreach ($this->participations as $participation) {
            if ($participation->isPaye()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Calcule le montant total de l'événement
     * Si un montant fixe est défini, on l'utilise
     * Sinon on somme les participations
     */
    public function getMontantTotal(): float
    {
        // Si un montant fixe est défini, l'utiliser
        if ($this->montantFixe && $this->montantFixe > 0) {
            return $this->montantFixe * $this->getTotalParticipants();
        }
        
        // Sinon, sommer les participations
        $total = 0;
        foreach ($this->participations as $participation) {
            $total += $participation->getMontantTotal() ?? 0;
        }
        return $total;
    }

    public function getMontantTotalPaye(): float
    {
        $total = 0;
        foreach ($this->participations as $participation) {
            if ($participation->isPaye()) {
                $total += $participation->getMontantTotal() ?? 0;
            }
        }
        return $total;
    }
}