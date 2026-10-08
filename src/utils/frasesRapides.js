// Frases ràpides dels quadres de text: es trien amb un clic i es poden retocar; escriure a mà
// sempre és possible. Totes superen el mínim de caràcters del camp on van.
//
// NO n'hi ha als textos que s'han de redactar expressament per a cada cas (documents, avisos
// legals, informe a la RLT, burofax, motivació i proves d'un expedient disciplinari).
export const FRASES = {
  // ── Gestió: fitxatges ──
  rebuig_fitxatge: [
    'Fitxatge duplicat: ja hi ha un altre registre del mateix dia.',
    'La jornada no correspon a cap servei assignat.',
    'Fitxatge fet per error: la persona no treballava aquest dia.',
    'Les hores no quadren amb el registre del servei.',
  ],
  rebuig_tram: [
    'Temps fora de zona sense justificació.',
    'Temps fora d\'horari no autoritzat.',
    'Tram duplicat o solapat amb un altre.',
    'No consta cap servei en aquest tram.',
  ],
  correccio_hora: [
    'Sortida oblidada: hora confirmada amb la persona.',
    'Entrada fitxada tard per error de l\'aplicació.',
    'Hora corregida segons el registre del servei.',
    'Hora declarada per la persona i validada per RRHH.',
  ],
  correccio_zona: [
    'Era en un centre que encara no tenia assignat.',
    'Error de posició del GPS a l\'interior de l\'edifici.',
    'Servei autoritzat fora de la zona habitual.',
    'Canvi de domicili del pacient no actualitzat.',
  ],
  revisar_de_nou: [
    'Cal revisar-lo de nou: s\'ha aprovat per error.',
    'Hi ha informació nova que canvia la decisió.',
    'La persona ha presentat una al·legació posterior.',
    'Revisió demanada per coordinació.',
  ],

  // ── Treballadors: fitxatges ──
  allegacio_tram: [
    'Error del GPS a l\'interior de l\'edifici.',
    'Estava atenent un pacient en un domicili nou.',
    'Desplaçament entre dos serveis assignats.',
    'Vaig fitxar des d\'un altre centre on em van enviar.',
  ],
  declaracio_sortida: [
    'Em vaig oblidar de fitxar la sortida.',
    'L\'aplicació no em deixava fitxar la sortida.',
    'Em vaig quedar sense bateria al mòbil.',
    'No tenia connexió a Internet en sortir.',
  ],
  justificacio_gps: [
    'El mòbil no troba la ubicació en aquest moment.',
    'Sense cobertura a l\'interior de l\'edifici.',
    'He hagut de fer servir el meu mòbil personal.',
    'El permís d\'ubicació està desactivat i no el puc activar ara.',
  ],

  // ── Permisos i excedències ──
  motiu_absencia: [
    'Visita mèdica programada.',
    'Assumptes propis.',
    'Acompanyament d\'un familiar al metge.',
    'Tràmit administratiu que no es pot fer fora de l\'horari.',
    'Malaltia: aportaré el justificant.',
  ],
  denegacio: [
    'No hi ha cobertura del servei per a aquestes dates.',
    'Ja hi ha massa persones de permís en aquestes dates.',
    'Cal sol·licitar-ho amb més antelació.',
    'Falta el justificant: torna-la a enviar amb el document.',
  ],
  motiu_excedencia: [
    'Cura d\'un fill o filla menor.',
    'Cura d\'un familiar dependent.',
    'Motius personals.',
    'Estudis o formació.',
  ],

  // ── Comitè, pactes, millores ──
  motiu_comite: [
    'Reunió ordinària del comitè.',
    'Reunió de negociació amb la direcció.',
    'Formació per a la representació legal.',
    'Atenció a consultes de la plantilla.',
  ],
  rebuig_comite: [
    'Les hores superen el crèdit horari disponible.',
    'No consta la convocatòria de la reunió.',
    'Data duplicada amb una altra sol·licitud.',
  ],
  revocacio_pacte: [
    'Ja no calen les hores complementàries.',
    'A petició de la persona treballadora.',
    'Canvi d\'organització del servei.',
  ],
  descartar_millora: [
    'Ja està resolt.',
    'No és possible fer-ho ara.',
    'Duplicada amb una altra proposta.',
  ],

  // ── Pràctiques ──
  observacions_practiques: [
    'Tutor/a assignat/da al centre.',
    'Horari pactat amb el centre educatiu.',
    'Pendent de rebre el conveni signat.',
  ],

  // ── Drets RGPD ──
  dret_rgpd_detall: [
    'Totes les meves dades personals.',
    'Les meves dades de fitxatge de l\'últim any.',
    'Les dades d\'ubicació dels meus fitxatges.',
  ],
  resposta_rgpd: [
    'S\'ha atès la sol·licitud: trobareu les dades a la vostra pantalla de documents.',
    'S\'han rectificat les dades indicades a la sol·licitud.',
    'No es pot atendre la sol·licitud perquè les dades s\'han de conservar per obligació legal.',
  ],
  rlt_lliurat_a: [
    'Comitè d\'empresa: presidència.',
    'Delegats/des de personal.',
  ],
  rlt_com: [
    'Per correu electrònic, amb acusament de recepció.',
    'En mà, amb signatura de recepció.',
  ],

  // ── Rendiment i expedients (la persona treballadora) ──
  allegacio_rendiment: [
    'Aquests dies vaig tenir més desplaçaments dels habituals entre serveis.',
    'Vaig atendre pacients amb més necessitats de les previstes.',
    'Hi va haver incidències a l\'aplicació que no van registrar bé la feina.',
  ],
  resposta_rendiment: [
    'S\'ha revisat l\'al·legació i s\'accepta: es tindrà en compte per al càlcul.',
    'S\'ha revisat l\'al·legació, però les dades del període no ho justifiquen.',
    'S\'ha revisat i se\'n parlarà amb la persona i la coordinació.',
  ],
  allegacio_disciplinari: [
    'No estic d\'acord amb els fets tal com estan descrits.',
    'Demano que es tingui en compte el context en què van passar els fets.',
    'Proposo com a prova el testimoni de les persones que hi eren.',
  ],

  // ── Xat ──
  xat: [
    'D\'acord, gràcies!',
    'Ara ho miro.',
    'Et responc en un moment.',
    'Perfecte, queda anotat.',
  ],
}

export function frases(clau) {
  return FRASES[clau] || []
}
