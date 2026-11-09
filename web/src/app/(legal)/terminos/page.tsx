import type { Metadata } from "next";

export const metadata: Metadata = { title: "Términos de uso" };

// Base text, version 2026-10-03 (= config('app.terms_version') in the API). PENDING LEGAL REVIEW.
export default function Page() {
  return (
    <>
      <h1>Términos de uso de Zooetia</h1>
      <p className="text-theme-sm text-gray-500">Versión del 3 de octubre de 2026 · Texto base pendiente de revisión legal.</p>

      <h2>1. Qué es Zooetia</h2>
      <p>
        Zooetia es un software en la nube para clínicas veterinarias: agenda, historia clínica, tutores y mascotas, recordatorios, consentimientos firmados en pantalla y
        portal para los tutores. Lo usa la clínica (en adelante, &quot;la clínica&quot;) por medio de las personas que ella autoriza.
      </p>

      <h2>2. Cuenta y prueba gratis</h2>
      <ul>
        <li>Quien crea la cuenta declara que representa a la clínica y queda como su administrador.</li>
        <li>La clínica tiene 15 días de prueba gratis con las funciones del plan Pro. No se pide tarjeta.</li>
        <li>Al terminar la prueba, la información sigue disponible para consulta; para registrar cambios hay que activar un plan.</li>
        <li>La clínica es responsable de quién tiene acceso, de los permisos que asigna y de mantener seguras las contraseñas.</li>
      </ul>

      <h2>3. Planes y pagos</h2>
      <ul>
        <li>Los planes, sus precios y lo que incluyen se muestran en Configuración → Plan y facturación.</li>
        <li>El pago es por adelantado, por mes. Si un pago no se recibe, hay unos días de gracia; después la cuenta pasa a solo lectura hasta que se pague.</li>
        <li>Los mensajes de WhatsApp automáticos tienen un límite mensual según el plan.</li>
      </ul>

      <h2>4. La información de la clínica</h2>
      <ul>
        <li>
          La información que la clínica registra (tutores, mascotas, historias clínicas, documentos) es de la clínica. Zooetia la trata solo para prestar el servicio,
          como encargado del tratamiento (ver la política de tratamiento de datos).
        </li>
        <li>La clínica responde por tener las autorizaciones de los tutores para tratar sus datos y para enviarles mensajes.</li>
        <li>La clínica puede exportar su información (por ejemplo, las historias clínicas en PDF) en cualquier momento.</li>
      </ul>

      <h2>5. Historia clínica y consentimientos</h2>
      <p>
        Zooetia conserva los registros clínicos y los documentos firmados sin borrarlos (se anulan con motivo) y guarda evidencias de cada firma electrónica (Ley 527
        de 1999). El contenido clínico y los textos de los consentimientos que use la clínica son responsabilidad de la clínica y de sus profesionales.
      </p>

      <h2>6. Uso aceptable</h2>
      <p>No se permite usar Zooetia para enviar mensajes no autorizados, intentar acceder a información de otras clínicas ni afectar el funcionamiento del servicio.</p>

      <h2>7. Disponibilidad y cambios</h2>
      <p>
        Hacemos copias de seguridad y trabajamos para que el servicio esté disponible siempre, pero puede haber interrupciones por mantenimiento o por causas externas.
        Si cambiamos estos términos, avisaremos a las clínicas; la versión aceptada por cada persona queda registrada.
      </p>

      <h2>8. Contacto</h2>
      <p>Para dudas sobre estos términos escríbenos desde la aplicación o al correo de soporte de Zooetia.</p>
    </>
  );
}
