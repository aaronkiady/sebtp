<?php

namespace App\Entity;

use App\Repository\LigneEvenementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LigneEvenementRepository::class)]
class LigneEvenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $designation = null;

    #[ORM\Column(nullable: true)]
    private ?float $montantUnitaire = null;

    #[ORM\ManyToOne(inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Evenement $evenement = null;

    /**
     * @var Collection<int, ParticipationLigne>
     */
    #[ORM\OneToMany(
        mappedBy: 'ligne',
        targetEntity: ParticipationLigne::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $participationLignes;

    public function __construct()
    {
        $this->participationLignes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDesignation(): ?string
    {
        return $this->designation;
    }

    public function setDesignation(?string $designation): static
    {
        $this->designation = $designation;

        return $this;
    }

    public function getMontantUnitaire(): ?float
    {
        return $this->montantUnitaire;
    }

    public function setMontantUnitaire(?float $montantUnitaire): static
    {
        $this->montantUnitaire = $montantUnitaire;

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
            $participationLigne->setLigne($this);
        }

        return $this;
    }

    public function removeParticipationLigne(ParticipationLigne $participationLigne): static
    {
        if ($this->participationLignes->removeElement($participationLigne)) {
            if ($participationLigne->getLigne() === $this) {
                $participationLigne->setLigne(null);
            }
        }

        return $this;
    }
}