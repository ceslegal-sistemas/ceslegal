# Reporte de capacidad - Descargos simultáneos

**Entorno:** plan Hostinger compartido (servidor de pruebas `descargos`, idéntico al de producción).
**Fecha de la medición:** junio 2026.

---

## Respuesta corta (para dirección)

> En el plan actual (hosting compartido), el sistema soporta de forma estable
> **alrededor de 150–300 descargos siendo diligenciados al mismo tiempo**.
> El máximo teórico antes de saturar ronda los **~500**.
>
> Esto se refiere a trabajadores **llenando el formulario simultáneamente** - NO al
> total de clientes/trabajadores, que puede ser de **miles** (no todos usan a la vez).

---

## En qué se basa (datos medidos)

| Medición | Resultado | Cómo se obtuvo |
|---|---|---|
| Latencia por cada respuesta enviada | **~3.2 s** | 15 llamadas reales a la IA en el servidor (p95 = 3.2 s, 0 fallos) |
| Concurrencia web liviana | **~20 usuarios sin problema** (0 errores, 241 ms) | Prueba de carga k6 contra el sitio |
| Capacidad de envíos sostenidos | **~6 envíos/segundo** | 20 procesos ÷ 3.2 s por envío |

Cada vez que un trabajador **envía una respuesta**, el sistema consulta la IA y eso
ocupa un proceso del servidor ~3.2 s. Ese es el factor que define el límite.

## El cálculo

```
Descargos simultáneos ≈ (procesos del plan) × (segundos entre envíos) / (3.2 s por envío)
```

Un trabajador no envía constantemente: lee y escribe, y envía una respuesta cada
~90 s. Con ~20 procesos:

| Ritmo de los trabajadores | Descargos simultáneos |
|---|---|
| Intenso (envío cada 45 s) | ~280 |
| Normal (envío cada 90 s) | ~555 (techo teórico) |
| **Realista y seguro** (con variación y tráfico real) | **~150–300** |

## Los límites honestos

1. **Riesgo de "ráfaga":** el tope instantáneo es **~20 envíos en la misma ventana de ~3 s**.
   Si se cita a 100 trabajadores **a la misma hora** y todos responden a la vez, algunos
   verán lentitud o error. **Mitigación: escalonar el envío de citaciones** (no todas juntas).
2. **Protección de Hostinger:** una sola fuente generando muchísima concurrencia es
   bloqueada por el hosting. Con usuarios reales esto **no aplica** (son IPs distintas),
   pero confirma que el plan compartido tiene barreras.
3. El número exacto escala con el **límite de procesos del plan** (asumimos ~20; si el
   plan permite 40, los números se duplican). Conviene confirmarlo en el panel del hosting.

## Total de clientes vs. simultáneos

- **Simultáneos** (a la vez, en este instante): ~150–300 en compartido.
- **Total de clientes/trabajadores** que puede gestionar el sistema (a lo largo de
  días/meses): **miles** - limitado por almacenamiento/base de datos, no por concurrencia.

## Para escalar a miles SIMULTÁNEOS

Migrar a **VPS/Cloud con worker dedicado (Redis)**: el envío liberaría el proceso al
instante y la IA correría en segundo plano. El techo pasaría de ~cientos a **miles**
de descargos simultáneos. Es el siguiente paso natural cuando el volumen lo exija.

---

### Conclusión

El plan actual **cubre con holgura el uso normal** (cientos de descargos en paralelo).
El único cuidado operativo es **no enviar todas las citaciones a la misma hora**.
Para crecimiento a miles concurrentes, el camino es VPS - con números medidos, no a ciegas.
