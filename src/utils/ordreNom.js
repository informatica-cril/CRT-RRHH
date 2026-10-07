// Detecta noms desats amb els cognoms davant («Domínguez Torres Leire») i en proposa l'ordre
// habitual («Leire Domínguez Torres»). És una proposta: la persona de RRHH la revisa abans de desar.
//
// Criteri: el nom acaba amb un o dos noms de pila habituals i comença per una paraula que NO ho és.
// Si porta coma («Domínguez Torres, Leire») es gira pel lloc de la coma.

const NOMS = `
aaron abel abril ada adam adela adolfo adria adriana adrian agata agnes agusti agustin aina ainara ainhoa aitana aitor aleix alejandra alejandro alex alexandra alexandre alexia alfonso alfred alfredo alicia alma almudena alvaro amaia amalia amanda amparo ana anabel andrea andreu andres angel angela angeles angelica angels anna annabel antoni antonia antonio arantxa arantza ariadna ariel arnau arturo asier assumpta aurora beatriz beatriu belen benjamin berta biel blanca borja bruno camila candela carina carla carles carlos carme carmen carol carolina catalina cecilia celia cesar charo chloe clara claudia concepcio concepcion consuelo cristian cristina cristobal dani daniel daniela dario david debora diana diego dolores dolors domingo dulce edgar eduard eduardo elena elisa elisabet elisabeth eloi elsa elvira emilia emilio emma enric enrique eric erica esperanza esteban ester esther estela eugenia eulalia eva fabian fatima federico felipe felix fermin fernanda fernando francesc francesca francisca francisco frandys frankeury gabriel gabriela gemma gerard gerardo gina gisela gloria gonzalo gregorio guadalupe guillem guillermo gustavo hector helena hugo ian ignacio ignasi ines inma inmaculada irene iris isaac isabel ismael ivan iolanda jaime jaume javier jenifer jennifer jesus jessica joan joana joaquim joaquin jordi jorge jose josefa josep josefina juan juana judit judith julia julian julio karen laia lara laura lea leila leire leonor lidia lluis lorena lourdes lucas lucia luis luisa lydia magdalena maite manel manuel manuela mar marc marcos margarita maria mariana marina mario marta marti martin mateo mateu matias maximo melissa merce mercedes miguel miquel mireia miriam mohamed monica montse montserrat naiara natalia nataliya nerea nicolas noelia noemi nora nuria olga oliver olivia omar oriol oscar pablo paloma pamela paola pascual patricia pau paula pedro pere pilar pol rafael ramon raquel raul rebeca ricard ricardo rita roberto rocio rodrigo roger rosa rosario ruben rut ruth salvador samuel sandra santiago sara sergi sergio silvia sofia sonia susana tania teresa tomas toni ulises valentina valeria vanesa vanessa vera veronica vicenc vicente victor victoria virginia xavier ximena yolanda zoe
`.trim().split(/\s+/)
const NOMS_SET = new Set(NOMS)
// Primeres paraules de noms compostos que no han de comptar com a cognom («Maria José»).
const PARTICULES = new Set(['de', 'del', 'la', 'las', 'los', 'i', 'y'])

const norm = (s) => String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
const esNom = (w) => NOMS_SET.has(norm(w))

/** Retorna el nom proposat o null si no sembla girat. */
export function proposaOrdre(nom) {
  const net = String(nom ?? '').trim().replace(/\s+/g, ' ')
  if (!net) return null
  // «Cognoms, Nom»
  const coma = net.split(',')
  if (coma.length === 2 && coma[1].trim()) return `${coma[1].trim()} ${coma[0].trim()}`
  const p = net.split(' ')
  if (p.length < 2 || esNom(p[0])) return null
  // Un o dos noms de pila al final (p. ex. «… Maria José»)
  let n = 0
  while (n < 2 && n < p.length - 1 && esNom(p[p.length - 1 - n])) n++
  if (n === 0) return null
  // Si el que queda davant també sembla nom, no es toca (ambigu).
  const davant = p.slice(0, p.length - n)
  if (davant.filter(w => !PARTICULES.has(norm(w))).every(esNom)) return null
  return [...p.slice(p.length - n), ...davant].join(' ')
}
