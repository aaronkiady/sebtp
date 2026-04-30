<?php

namespace App\Entity;

use App\Repository\FormationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FormationRepository::class)]
class Formation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $dateDebut = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $dateFin = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $organisateur = null;

    /**
     * @var Collection<int, Liste>
     */
    #[ORM\ManyToMany(targetEntity: Liste::class, inversedBy: 'formations')]
    private Collection $participants;

    /**
     * @var array Les détails des participants (noms des agents par adhérent)
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $participantsDetails = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $remarque = null;

    public function __construct()
    {
        $this->participants = new ArrayCollection();
        $this->participantsDetails = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getDateDebut(): ?\DateTime
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTime $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTime
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTime $dateFin): static
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getOrganisateur(): ?string
    {
        return $this->organisateur;
    }

    public function setOrganisateur(?string $organisateur): static
    {
        $this->organisateur = $organisateur;
        return $this;
    }

    /**
     * @return Collection<int, Liste>
     */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(Liste $participant): static
    {
        if (!$this->participants->contains($participant)) {
            $this->participants->add($participant);
            $participant->addFormation($this);
        }
        return $this;
    }

    public function removeParticipant(Liste $participant): static
    {
        if ($this->participants->removeElement($participant)) {
            $participant->removeFormation($this);
        }
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

    public function getRemarque(): ?string
    {
        return $this->remarque;
    }

    public function setRemarque(?string $remarque): static
    {
        $this->remarque = $remarque;

        return $this;
    }

    public function getParticipantsDetails(): ?array
    {
        return $this->participantsDetails;
    }

    public function setParticipantsDetails(?array $participantsDetails): static
    {
        $this->participantsDetails = $participantsDetails;
        return $this;
    }

    /**
     * Récupère les détails pour un participant spécifique
     */
    public function getParticipantDetail(int $participantId): ?string
    {
        return $this->participantsDetails[$participantId] ?? null;
    }

    /**
     * Ajoute un détail pour un participant
     */
    public function setParticipantDetail(int $participantId, string $detail): static
    {
        $this->participantsDetails[$participantId] = $detail;
        return $this;
    }
}