/* =====================================================================
   RIFAS - DINÁMICAS DEL EVENTO
   Tablas nuevas para registrar ganadores, boletas redimidas y el
   "espejo" (foto) de las compras al corte de las 12:00.
   Ejecutar una sola vez en la base passport_roma_v1.
   ===================================================================== */

/* ---------------------------------------------------------------------
   Ganadores de cada dinámica.
   DINAMICA: RULETA | IPHONE_PRO | IPHONE_BONO | ELECTRO
   - BOLETAS            : boletas ganadas al momento del registro
   - BOLETAS_REDIMIDAS  : boletas efectivamente entregadas/impresas
   - REDIMIDO           : 1 cuando BOLETAS_REDIMIDAS = BOLETAS
   - COMPRA_TOTAL/LABS  : valor de la compra en el momento en que ganó
   --------------------------------------------------------------------- */
IF OBJECT_ID('dbo.T_RIFA_DINAMICAS_GANADORES') IS NULL
BEGIN
  CREATE TABLE [dbo].[T_RIFA_DINAMICAS_GANADORES] (
    [ID]                int IDENTITY(1, 1) NOT NULL,
    [DINAMICA]          varchar(20)  COLLATE Modern_Spanish_CI_AS NOT NULL,
    [PASSPORT]          varchar(10)  COLLATE Modern_Spanish_CI_AS NOT NULL,
    [NOMBRES]           varchar(120) COLLATE Modern_Spanish_CI_AS NULL,
    [GRUPO]             char(3)      COLLATE Modern_Spanish_CI_AS NULL,
    [COMPRA_TOTAL]      numeric(18, 0) DEFAULT 0 NOT NULL,
    [COMPRA_LABS]       numeric(18, 0) DEFAULT 0 NOT NULL,
    [N_LABS]            int DEFAULT 0 NOT NULL,
    [BOLETAS]           int DEFAULT 1 NOT NULL,
    [BOLETAS_REDIMIDAS] int DEFAULT 0 NOT NULL,
    [REDIMIDO]          bit DEFAULT 0 NOT NULL,
    [FECHA_HORA]        datetime DEFAULT GETDATE() NOT NULL,
    [USUARIO]           varchar(16)  COLLATE Modern_Spanish_CI_AS NULL,
    [FECHA_REDENCION]   datetime NULL,
    [USUARIO_REDENCION] varchar(16)  COLLATE Modern_Spanish_CI_AS NULL,
    CONSTRAINT [PK_T_RIFA_DINAMICAS_GANADORES] PRIMARY KEY CLUSTERED ([ID]),
    -- Un cliente gana una sola vez cada dinámica
    CONSTRAINT [UQ_T_RIFA_DINAMICAS_GANADORES] UNIQUE ([DINAMICA], [PASSPORT])
  ) ON [PRIMARY]
END
GO

/* ---------------------------------------------------------------------
   Espejo de las 12:00: control (una fila por corte)
   --------------------------------------------------------------------- */
IF OBJECT_ID('dbo.T_RIFA_ESPEJO_CONTROL') IS NULL
BEGIN
  CREATE TABLE [dbo].[T_RIFA_ESPEJO_CONTROL] (
    [ID]               int IDENTITY(1, 1) NOT NULL PRIMARY KEY,
    [FECHA_CORTE]      datetime NOT NULL,
    [FECHA_GENERACION] datetime DEFAULT GETDATE() NOT NULL,
    [CLIENTES]         int DEFAULT 0 NOT NULL,
    [USUARIO]          varchar(16) COLLATE Modern_Spanish_CI_AS NULL,
    CONSTRAINT [UQ_T_RIFA_ESPEJO_CONTROL] UNIQUE ([FECHA_CORTE])
  ) ON [PRIMARY]
END
GO

/* Espejo: totales por cliente al corte */
IF OBJECT_ID('dbo.T_RIFA_ESPEJO') IS NULL
BEGIN
  CREATE TABLE [dbo].[T_RIFA_ESPEJO] (
    [ID]           int IDENTITY(1, 1) NOT NULL PRIMARY KEY,
    [FECHA_CORTE]  datetime NOT NULL,
    [PASSPORT]     varchar(10) COLLATE Modern_Spanish_CI_AS NOT NULL,
    [GRUPO]        char(3)     COLLATE Modern_Spanish_CI_AS NULL,
    [COMPRA_TOTAL] numeric(18, 0) DEFAULT 0 NOT NULL,
    [COMPRA_LABS]  numeric(18, 0) DEFAULT 0 NOT NULL,
    [N_LABS]       int DEFAULT 0 NOT NULL,   -- labs participantes con compra > 0
    CONSTRAINT [UQ_T_RIFA_ESPEJO] UNIQUE ([FECHA_CORTE], [PASSPORT])
  ) ON [PRIMARY]
END
GO

/* Espejo: detalle por cliente y laboratorio participante al corte */
IF OBJECT_ID('dbo.T_RIFA_ESPEJO_LABS') IS NULL
BEGIN
  CREATE TABLE [dbo].[T_RIFA_ESPEJO_LABS] (
    [ID]          int IDENTITY(1, 1) NOT NULL PRIMARY KEY,
    [FECHA_CORTE] datetime NOT NULL,
    [PASSPORT]    varchar(10) COLLATE Modern_Spanish_CI_AS NOT NULL,
    [NIT]         varchar(30) COLLATE Modern_Spanish_CI_AS NOT NULL,
    [COMPRA]      numeric(18, 0) DEFAULT 0 NOT NULL
  ) ON [PRIMARY]

  CREATE NONCLUSTERED INDEX [IX_T_RIFA_ESPEJO_LABS_PASS]
    ON [dbo].[T_RIFA_ESPEJO_LABS] ([FECHA_CORTE], [PASSPORT]) INCLUDE ([NIT], [COMPRA])
END
GO

/* ---------------------------------------------------------------------
   Índices de apoyo (recomendados)
   --------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_T_MATERIALES_CODIGO_EV'
               AND object_id = OBJECT_ID('dbo.T_MATERIALES'))
  CREATE NONCLUSTERED INDEX [IX_T_MATERIALES_CODIGO_EV]
    ON [dbo].[T_MATERIALES] ([CODIGO_MATERIAL]) INCLUDE ([CODIGO_SAP])
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_T_USUARIOS_CODIGO_SAP_EV'
               AND object_id = OBJECT_ID('dbo.T_USUARIOS'))
  CREATE NONCLUSTERED INDEX [IX_T_USUARIOS_CODIGO_SAP_EV]
    ON [dbo].[T_USUARIOS] ([CODIGO_SAP]) INCLUDE ([TIPO_USUARIO])
GO

/* ---------------------------------------------------------------------
   Reinicio para pruebas (NO ejecutar el día del evento)
   ---------------------------------------------------------------------
   DELETE FROM T_RIFA_DINAMICAS_GANADORES;
   DELETE FROM T_RIFA_ESPEJO_LABS;
   DELETE FROM T_RIFA_ESPEJO;
   DELETE FROM T_RIFA_ESPEJO_CONTROL;
*/
