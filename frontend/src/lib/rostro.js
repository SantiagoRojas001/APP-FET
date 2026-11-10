/**
 * Revisión de la foto de la hoja de vida en el navegador, con el detector de
 * rostros de MediaPipe (el modelo y el WebAssembly se sirven desde
 * /mediapipe/; la foto no sale del equipo para esta revisión). Exige un solo
 * rostro, de buen tamaño, y recorta la foto en formato documento (4:5)
 * alrededor de la cara. La API solo acepta ese formato.
 */

const ANCHO = 480;
const ALTO = 600;

let detector = null;

async function cargarDetector() {
  if (detector) return detector;
  const { FaceDetector, FilesetResolver } = await import('@mediapipe/tasks-vision');
  const archivos = await FilesetResolver.forVisionTasks('/mediapipe');
  detector = await FaceDetector.createFromOptions(archivos, {
    baseOptions: { modelAssetPath: '/mediapipe/blaze_face_short_range.tflite' },
    runningMode: 'IMAGE',
    minDetectionConfidence: 0.6,
  });
  return detector;
}

/** Devuelve la foto recortada (JPEG) o lanza un Error con el motivo del rechazo. */
export async function recortarRostro(archivo) {
  let imagen;
  try {
    imagen = await createImageBitmap(archivo, { imageOrientation: 'from-image' });
  } catch {
    throw new Error('No pudimos abrir la imagen. Usa una foto en JPG o PNG.');
  }

  let caras;
  try {
    caras = (await cargarDetector()).detect(imagen).detections;
  } catch {
    throw new Error('No pudimos revisar la foto en este navegador. Inténtalo con Chrome, Edge o Firefox actualizados.');
  }

  if (caras.length === 0) throw new Error('No encontramos un rostro en la foto. Usa una foto tuya, de frente y con buena luz.');
  if (caras.length > 1) throw new Error('En la foto aparece más de una persona. Usa una foto solo tuya.');

  const cara = caras[0].boundingBox;
  if (cara.width < imagen.width * 0.12) throw new Error('Tu rostro se ve muy pequeño. Usa una foto más cercana, como la de un documento.');

  // Cabeza y hombros: el rostro ocupa cerca del 40 % del alto, un poco por encima del centro.
  let alto = Math.min(cara.height * 2.5, imagen.height, (imagen.width * ALTO) / ANCHO);
  let ancho = (alto * ANCHO) / ALTO;
  if (ancho > imagen.width) [ancho, alto] = [imagen.width, (imagen.width * ALTO) / ANCHO];
  const x = Math.min(Math.max(0, cara.originX + cara.width / 2 - ancho / 2), imagen.width - ancho);
  const y = Math.min(Math.max(0, cara.originY - cara.height * 0.6), imagen.height - alto);

  const lienzo = Object.assign(document.createElement('canvas'), { width: ANCHO, height: ALTO });
  lienzo.getContext('2d').drawImage(imagen, x, y, ancho, alto, 0, 0, ANCHO, ALTO);
  imagen.close();

  return new Promise((resolve) => lienzo.toBlob(resolve, 'image/jpeg', 0.9));
}
