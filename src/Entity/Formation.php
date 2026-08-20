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

    // MODIFICATION : 'type' devient 'formateurs' pour stocker le nom des formateurs
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $formateurs = null;

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
     * @var array Stocke le nombre de formés par adhérent (clé = id adhérent, valeur = nombre)
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $nombresFormes = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $remarque = null;

    public function __construct()
    {
        $this->participants = new ArrayCollection();
        $this->nombresFormes = [];
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

    public function getFormateurs(): ?string
    {
        return $this->formateurs;
    }

    public function setFormateurs(?string $formateurs): static
    {
        $this->formateurs = $formateurs;
        return $this;
    }

    // Méthode de compatibilité pour l'ancien champ 'type'
    public function getType(): ?string
    {
        return $this->formateurs;
    }

    public function setType(?string $formateurs): static
    {
        $this->formateurs = $formateurs;
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

    public function getNombresFormes(): ?array
    {
        return $this->nombresFormes;
    }

    public function setNombresFormes(?array $nombresFormes): static
    {
        $this->nombresFormes = $nombresFormes;
        return $this;
    }

    /**
     * Récupère le nombre de formés pour un participant spécifique
     */
    public function getNombreFormes(int $participantId): ?int
    {
        return $this->nombresFormes[$participantId] ?? null;
    }

    /**
     * Définit le nombre de formés pour un participant
     */
    public function setNombreFormes(int $participantId, int $nombre): static
    {
        $this->nombresFormes[$participantId] = $nombre;
        return $this;
    }

    /**
     * Calcule le nombre total de bénéficiaires (formés)
     */
    public function getTotalBeneficiaires(): int
    {
        $total = 0;
        foreach ($this->nombresFormes ?? [] as $nombre) {
            $total += (int) $nombre;
        }
        return $total;
    }

    /**
     * Retourne le nombre de participants (adhérents)
     */
    public function getNbParticipants(): int
    {
        return $this->participants->count();
    }

    /**
     * Retourne la période de la formation
     */
    public function getPeriode(): string
    {
        if ($this->dateDebut && $this->dateFin) {
            return $this->dateDebut->format('d/m/Y') . ' - ' . $this->dateFin->format('d/m/Y');
        }
        return 'Non définie';
    }

    /**
     * Retourne la durée en jours
     */
    public function getDuree(): int
    {
        if ($this->dateDebut && $this->dateFin) {
            return $this->dateFin->diff($this->dateDebut)->days;
        }
        return 0;
    }
}