import type { Metadata } from "next";

export const metadata: Metadata = { title: "Política de tratamiento de datos" };

// Base text, version 2026-10-03 (= config('app.terms_version') in the API). PENDING LEGAL REVIEW.
export default function Page() {
  return (
    <>
      <h1>Política de tratamiento de datos personales</h1>
      <p className="text-theme-sm text-gray-500">Ley 1581 de 2012 y Decreto 1377 de 2013 · Versión del 3 de octubre de 2026 · Texto base pendiente de revisión legal.</p>

      <h2>1. Dos tipos de datos, dos roles</h2>
      <ul>
        <li>
          <b>Datos de las personas que usan Zooetia</b> (equipo de la clínica): Zooetia es <b>responsable</b> del tratamiento. Los usamos para dar acceso, soporte y
          facturación del servicio.
        </li>
        <li>
          <b>Datos de los tutores y sus mascotas</b> que registra cada clínica: la clínica es la <b>responsable</b> y Zooetia es <b>encargado</b>: los tratamos solo por
          cuenta de la clínica y para prestarle el servicio.
        </li>
      </ul>

      <h2>2. Qué datos tratamos</h2>
      <ul>
        <li>Del equipo de la clínica: nombre, correo, celular, rol, tarjeta profesional y registros de uso (por ejemplo, quién creó o modificó un registro).</li>
        <li>De los tutores: nombre, documento, contacto, dirección y sus autorizaciones; de las mascotas: datos e historia clínica.</li>
        <li>De las firmas electrónicas: trazo, fecha y hora, dispositivo y dirección IP, como evidencia.</li>
      </ul>

      <h2>3. Para qué</h2>
      <ul>
        <li>Prestar el servicio: agenda, historia clínica, recordatorios, documentos y portal del tutor.</li>
        <li>Enviar mensajes a los tutores solo por los canales que cada uno autorizó en su clínica.</li>
        <li>Seguridad, auditoría y cumplimiento legal.</li>
      </ul>

      <h2>4. Autorización</h2>
      <p>
        Al crear una cuenta, la persona autoriza el tratamiento de sus datos según esta política; la fecha y la versión aceptada quedan registradas. Cada clínica obtiene y
        registra en Zooetia la autorización de sus tutores (tratamiento de datos obligatorio y cada canal de contacto por separado).
      </p>

      <h2>5. Derechos de los titulares</h2>
      <p>
        Conocer, actualizar, rectificar y suprimir sus datos, revocar la autorización y pedir prueba de ella. Los tutores ejercen estos derechos ante su clínica; el portal
        del tutor les permite consultar sus datos. Las personas del equipo pueden hacerlo ante Zooetia.
      </p>

      <h2>6. Seguridad</h2>
      <p>
        Cada clínica solo ve su propia información. Las contraseñas y enlaces se guardan cifrados, las sesiones son seguras y las fotos se procesan para quitar datos de
        ubicación. Los registros clínicos no se borran: se anulan, como exige la historia clínica.
      </p>

      <h2>7. Transferencias y proveedores</h2>
      <p>
        Usamos proveedores para alojar el servicio y enviar mensajes (por ejemplo, WhatsApp de Meta y correo), con los que compartimos solo lo necesario para prestar el
        servicio.
      </p>

      <h2>8. Contacto y vigencia</h2>
      <p>Para consultas y reclamos sobre datos personales escríbenos desde la aplicación o al correo de soporte de Zooetia. Esta política rige desde su publicación.</p>
    </>
  );
}
