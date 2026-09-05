<?php
/**
 * El formulario de una ficha: sirve para agregar y para editar.
 *
 * La diferencia entre los dos usos está en $editando, y se ve en dos sitios:
 * la llave va de solo lectura al editar, y aparecen DOS botones de guardar
 * en vez de uno.
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
  <div>
    <h1 class="h3 mb-1"><?= $editando ? 'Editar la ficha' : 'Agregar el aliado' ?></h1>
    <p class="text-body-secondary mb-0">
      <?php if ($editando): ?>
        La llave identifica la ficha y no se cambia. Si está mal, se agrega
        otra y se retira ésta.
      <?php else: ?>
        La llave la escribe usted y no se podrá cambiar después.
      <?php endif; ?>
    </p>
  </div>
  <a class="btn btn-outline-secondary" href="/aliados">Volver al listado</a>
</div>

<div class="card shadow-sm" style="max-width: 46rem;">
  <div class="card-body p-4">
    <form method="post">

      <div class="mb-3">
        <label class="form-label" for="nit">NIT</label>
        <input class="form-control font-monospace" type="number" id="nit" name="nit" min="0"
               value="<?= htmlspecialchars((string) ($ficha['nit'] ?? '')) ?>"
               <?= $editando ? 'readonly' : 'required autofocus' ?>>
        <div class="form-text">El NIT de la organización, sin puntos ni guion.</div>
      </div>

      <div class="mb-3">
        <label class="form-label" for="razon_social">Razón social</label>
        <input class="form-control" type="text" id="razon_social" name="razon_social"
               maxlength="60"
               value="<?= htmlspecialchars((string) ($ficha['razon_social'] ?? '')) ?>">
      </div>

      <div class="mb-3">
        <label class="form-label" for="nombre_contacto">Nombre del contacto</label>
        <input class="form-control" type="text" id="nombre_contacto" name="nombre_contacto"
               maxlength="60"
               value="<?= htmlspecialchars((string) ($ficha['nombre_contacto'] ?? '')) ?>">
      </div>

      <div class="mb-3">
        <label class="form-label" for="correo">Correo</label>
        <input class="form-control" type="text" id="correo" name="correo"
               maxlength="70"
               value="<?= htmlspecialchars((string) ($ficha['correo'] ?? '')) ?>">
      </div>

      <div class="mb-3">
        <label class="form-label" for="telefono">Teléfono</label>
        <input class="form-control" type="text" id="telefono" name="telefono"
               maxlength="45"
               value="<?= htmlspecialchars((string) ($ficha['telefono'] ?? '')) ?>">
      </div>

      <div class="mb-3">
        <label class="form-label" for="ciudad">Ciudad</label>
        <input class="form-control" type="text" id="ciudad" name="ciudad"
               maxlength="45"
               value="<?= htmlspecialchars((string) ($ficha['ciudad'] ?? '')) ?>">
      </div>

      <hr class="my-4">

      <?php /* ==============================================================
           LOS DOS BOTONES, QUE NO HACEN LO MISMO

             · «Guardar la ficha completa» manda todo, así que un dato
               obligatorio en blanco se rechaza.
             · «Guardar solo lo que cambié» manda únicamente lo diligenciado,
               así que el mismo formulario a medio llenar sí se guarda.

           El mismo formulario, dos comportamientos, y la diferencia no la
           decide ningún `if` de negocio: la decide QUÉ SE ENVÍA.
           ============================================================== */ ?>
      <?php if ($editando): ?>
        <div class="d-flex flex-wrap gap-2">
          <button class="btn btn-primary" type="submit" name="verbo" value="completa">
            Guardar la ficha completa
          </button>
          <button class="btn btn-outline-primary" type="submit" name="verbo" value="parcial">
            Guardar solo lo que cambié
          </button>
        </div>
        <div class="form-text mt-3">
          <strong>«La ficha completa»</strong> exige que todos los datos
          obligatorios estén diligenciados. <strong>«Solo lo que cambié»</strong>
          guarda lo que usted escribió y deja lo demás como estaba.
        </div>
      <?php else: ?>
        <button class="btn btn-primary" type="submit">Agregar</button>
      <?php endif; ?>

    </form>
  </div>
</div>
