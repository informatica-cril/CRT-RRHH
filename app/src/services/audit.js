import { db } from './db'

export async function auditLog(userId, action, entityType = null, entityId = null, details = null) {
  try {
    await db.addAuditLog({
      user_id: userId,
      action,
      entity_type: entityType,
      entity_id: entityId,
      description: details,
      ip_address: '127.0.0.1', // In production: from server
      user_agent: navigator.userAgent,
      created_at: new Date().toISOString()
    })
  } catch (err) {
    console.warn('[Audit] Failed to log action:', action, err)
  }
}
