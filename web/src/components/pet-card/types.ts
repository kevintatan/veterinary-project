export type CardTemplate = "classic" | "modern" | "paws";

export type CardDesign = {
  template: CardTemplate;
  color: string;
  show_photo: boolean;
  show_owner_phone: boolean;
  show_vaccines: boolean;
};

/** What the card draws (staff designer and owner portal). */
export type CardInfo = {
  pet: {
    id: string;
    name: string;
    species: string | null;
    species_code: string | null;
    breed: string | null;
    sex: string | null;
    birth_date: string | null;
    birth_date_is_estimated: boolean;
    color: string | null;
    microchip: string | null;
    photo_url: string | null;
    code: string;
  };
  owner: { full_name: string | null; mobile_phone: string | null };
  clinic: { name: string; phone: string | null; logo_url: string | null };
  public_url: string;
  qr_url: string;
  vaccinations: { vaccine: string; applied_on: string; valid_until: string | null; status: "valid" | "expired" | "no_booster"; label: string }[];
  design: CardDesign;
};

/** The designer also gets the clinic's default design. */
export type CardData = CardInfo & {
  clinic_design: CardDesign;
  has_own_design: boolean;
};

export const TEMPLATES: { value: CardTemplate; label: string; description: string }[] = [
  { value: "classic", label: "Clásico", description: "Como una cédula: sobrio, con fondo de seguridad." },
  { value: "modern", label: "Moderno", description: "Degradado de color y la foto en primer plano." },
  { value: "paws", label: "Huellitas", description: "Alegre, con huellas de fondo." },
];

export const CARD_COLORS: { value: string; label: string }[] = [
  { value: "#166534", label: "Verde bosque" },
  { value: "#0F766E", label: "Turquesa" },
  { value: "#1D4ED8", label: "Azul" },
  { value: "#4338CA", label: "Índigo" },
  { value: "#7C3AED", label: "Morado" },
  { value: "#DB2777", label: "Rosa" },
  { value: "#E11D48", label: "Rojo" },
  { value: "#EA580C", label: "Naranja" },
  { value: "#CA8A04", label: "Mostaza" },
  { value: "#1F2937", label: "Grafito" },
];
