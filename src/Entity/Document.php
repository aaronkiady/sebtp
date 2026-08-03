<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'document')]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 50)]
    private ?string $numero = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\ManyToOne(targetEntity: Liste::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Liste $adherent = null;

    #[ORM\Column(nullable: true)]
    private ?float $montant = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cheminFichier = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nomFichier = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $contenu = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $periode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(nullable: true)]
    private ?float $prixUnitaire = null;

    #[ORM\Column(nullable: true)]
    private ?int $quantite = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->numero = $this->genererNumero();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(string $numero): static
    {
        $this->numero = $numero;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
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

    public function setMontant(?float $montant): static
    {
        $this->montant = $montant;
        return $this;
    }

    public function getCheminFichier(): ?string
    {
        return $this->cheminFichier;
    }

    public function setCheminFichier(?string $cheminFichier): static
    {
        $this->cheminFichier = $cheminFichier;
        return $this;
    }

    public function getNomFichier(): ?string
    {
        return $this->nomFichier;
    }

    public function setNomFichier(?string $nomFichier): static
    {
        $this->nomFichier = $nomFichier;
        return $this;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(?string $contenu): static
    {
        $this->contenu = $contenu;
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

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;
        return $this;
    }

    private function genererNumero(): string
    {
        $prefix = 'DOC';
        $year = date('Y');
        $random = str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        return sprintf('%s-%s-%s', $prefix, $year, $random);
    }

    public function getPrixUnitaire(): ?float
    {
        return $this->prixUnitaire;
    }

    public function setPrixUnitaire(?float $prixUnitaire): static
    {
        $this->prixUnitaire = $prixUnitaire;

        return $this;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(?int $quantite): static
    {
        $this->quantite = $quantite;

        return $this;
    }
}