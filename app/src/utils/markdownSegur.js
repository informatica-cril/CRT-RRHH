/**
 * Render de markdown mínim i segur per als textos legals que arriben del servidor.
 *
 * Escapa SEMPRE abans de formatar: el contingut d'un document de compliment l'escriu una persona
 * des d'un formulari i acaba en un v-html, de manera que HTML cru que hi entrés s'executaria a la
 * pantalla de qui el llegeix. Només es reconstrueixen títols, negretes i llistes.
 *
 * Viu aquí i no dins d'una vista perquè el fan servir la pantalla de compliment i la de privadesa:
 * dues còpies del mateix escapat és una còpia que algun dia es quedarà sense arreglar.
 */
export function renderMarkdownSegur(md) {
  const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  return esc(md || '')
    .replace(/^### (.*)$/gm, '<h4>$1</h4>')
    .replace(/^## (.*)$/gm, '<h3>$1</h3>')
    .replace(/^# (.*)$/gm, '<h3>$1</h3>')
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/^- (.*)$/gm, '<li>$1</li>')
    .replace(/\n{2,}/g, '</p><p>')
    .replace(/\n/g, '<br>')
    .replace(/^/, '<p>').replace(/$/, '</p>')
}
