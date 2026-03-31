<?php

namespace App\Entity;

use App\Repository\ListeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ListeRepository::class)]
class Liste
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $numero = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $siteWeb = null;

    #[ORM\Column(length: 255)]
    private ?string $activite = null;

    #[ORM\Column(length: 255)]
    private ?string $filiere = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nbEmployes = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cotFMTP = null;

    #[ORM\Column(length: 255)]
    private ?string $dg = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresseDg = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $telephoneDg = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $statutMenmbre = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fonctionSEBTP = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mandat = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $observation = null;

    /**
     * @var Collection<int, Formation>
     */
    #[ORM\ManyToMany(targetEntity: Formation::class, mappedBy: 'participants')]
    private Collection $formations;

    /**
     * @var Collection<int, Cotisation>
     */
    #[ORM\OneToMany(mappedBy: 'adherent', targetEntity: Cotisation::class, orphanRemoval: true)]
    private Collection $cotisations;

    /**
     * @var Collection<int, Evenement>
     */
    #[ORM\ManyToMany(targetEntity: Evenement::class, mappedBy: 'participants')]
    private Collection $evenements;

    public function __construct()
    {
        $this->formations = new ArrayCollection();
        $this->cotisations = new ArrayCollection();
        $this->evenements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(?string $numero): static
    {
        $this->numero = $numero;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;
        return $this;
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

    public function getSiteWeb(): ?string
    {
        return $this->siteWeb;
    }

    public function setSiteWeb(?string $siteWeb): static
    {
        $this->siteWeb = $siteWeb;
        return $this;
    }

    public function getActivite(): ?string
    {
        return $this->activite;
    }

    public function setActivite(string $activite): static
    {
        $this->activite = $activite;
        return $this;
    }

    public function getFiliere(): ?string
    {
        return $this->filiere;
    }

    public function setFiliere(string $filiere): static
    {
        $this->filiere = $filiere;
        return $this;
    }

    public function getNbEmployes(): ?string
    {
        return $this->nbEmployes;
    }

    public function setNbEmployes(?string $nbEmployes): static
    {
        $this->nbEmployes = $nbEmployes;
        return $this;
    }

    public function getCotFMTP(): ?string
    {
        return $this->cotFMTP;
    }

    public function setCotFMTP(?string $cotFMTP): static
    {
        $this->cotFMTP = $cotFMTP;
        return $this;
    }

    public function getDg(): ?string
    {
        return $this->dg;
    }

    public function setDg(string $dg): static
    {
        $this->dg = $dg;
        return $this;
    }

    public function getAdresseDg(): ?string
    {
        return $this->adresseDg;
    }

    public function setAdresseDg(?string $adresseDg): static
    {
        $this->adresseDg = $adresseDg;
        return $this;
    }

    public function getTelephoneDg(): ?string
    {
        return $this->telephoneDg;
    }

    public function setTelephoneDg(?string $telephoneDg): static
    {
        $this->telephoneDg = $telephoneDg;
        return $this;
    }

    public function getStatutMenmbre(): ?string
    {
        return $this->statutMenmbre;
    }

    public function setStatutMenmbre(?string $statutMenmbre): static
    {
        $this->statutMenmbre = $statutMenmbre;
        return $this;
    }

    public function getFonctionSEBTP(): ?string
    {
        return $this->fonctionSEBTP;
    }

    public function setFonctionSEBTP(?string $fonctionSEBTP): static
    {
        $this->fonctionSEBTP = $fonctionSEBTP;
        return $this;
    }

    public function getMandat(): ?string
    {
        return $this->mandat;
    }

    public function setMandat(?string $mandat): static
    {
        $this->mandat = $mandat;
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

    /**
     * @return Collection<int, Formation>
     */
    public function getFormations(): Collection
    {
        return $this->formations;
    }

    public function addFormation(Formation $formation): static
    {
        if (!$this->formations->contains($formation)) {
            $this->formations->add($formation);
            $formation->addParticipant($this);
        }
        return $this;
    }

    public function removeFormation(Formation $formation): static
    {
        if ($this->formations->removeElement($formation)) {
            $formation->removeParticipant($this);
        }
        return $this;
    }

    /**
     * @return Collection<int, Cotisation>
     */
    public function getCotisations(): Collection
    {
        return $this->cotisations;
    }

    public function addCotisation(Cotisation $cotisation): static
    {
        if (!$this->cotisations->contains($cotisation)) {
            $this->cotisations->add($cotisation);
            $cotisation->setAdherent($this);
        }
        return $this;
    }

    public function removeCotisation(Cotisation $cotisation): static
    {
        if ($this->cotisations->removeElement($cotisation)) {
            if ($cotisation->getAdherent() === $this) {
                $cotisation->setAdherent(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Evenement>
     */
    public function getEvenements(): Collection
    {
        return $this->evenements;
    }

    public function addEvenement(Evenement $evenement): static
    {
        if (!$this->evenements->contains($evenement)) {
            $this->evenements->add($evenement);
            $evenement->addParticipant($this);
        }
        return $this;
    }

    public function removeEvenement(Evenement $evenement): static
    {
        if ($this->evenements->removeElement($evenement)) {
            $evenement->removeParticipant($this);
        }
        return $this;
    }

    public function isAjour(): bool
    {
        $currentYear = date('Y');
        foreach ($this->cotisations as $cotisation) {
            if ($cotisation->getPeriode() === $currentYear && $cotisation->getStatut() === 'payé') {
                return true;
            }
        }
        return false;
    }
}