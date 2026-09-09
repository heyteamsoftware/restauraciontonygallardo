Add-Type -AssemblyName System.Drawing

$codigo = @'
using System;
using System.Text;
using System.Drawing;
using System.Drawing.Imaging;
using System.Collections.Generic;

public class Segmento
{
    public int X0, X1;
    public bool CorteIzq, CorteDer;
}

public static class Recortador
{
    static int ancho, alto;
    static bool[,] mascara;
    static byte[] pixeles;   // BGRA
    static int paso;

    public static string Procesar(string origen, string destino, string[] nombres, int[][] bandas, int relleno)
    {
        StringBuilder log = new StringBuilder();

        Bitmap bmp = new Bitmap(origen);
        ancho = bmp.Width; alto = bmp.Height;
        BitmapData d = bmp.LockBits(new Rectangle(0, 0, ancho, alto), ImageLockMode.ReadOnly, PixelFormat.Format32bppArgb);
        paso = d.Stride;
        pixeles = new byte[paso * alto];
        System.Runtime.InteropServices.Marshal.Copy(d.Scan0, pixeles, 0, pixeles.Length);
        bmp.UnlockBits(d); bmp.Dispose();

        mascara = new bool[ancho, alto];
        for (int y = 0; y < alto; y++)
            for (int x = 0; x < ancho; x++)
                mascara[x, y] = pixeles[y * paso + x * 4 + 3] > 20;

        // --- Paso 1: por cada fila, sacar los 10 segmentos y su contenido util ---
        List<bool[,]> recortes = new List<bool[,]>();   // mascara del icono, en coordenadas absolutas acotadas
        List<int[]> cajas = new List<int[]>();          // x0,y0,x1,y1
        int maxAncho = 0, maxAlto = 0;

        for (int f = 0; f < bandas.Length; f++)
        {
            int y0 = bandas[f][0], y1 = bandas[f][1];
            List<Segmento> segmentos = SegmentarFila(y0, y1);
            log.AppendLine("FILA " + (f + 1) + ": " + segmentos.Count + " segmentos");

            foreach (Segmento s in segmentos)
            {
                bool[,] limpio = ComponentesUtiles(s, y0, y1);
                int cx0 = int.MaxValue, cy0 = int.MaxValue, cx1 = -1, cy1 = -1;
                for (int x = s.X0; x <= s.X1; x++)
                    for (int y = y0; y <= y1; y++)
                        if (limpio[x - s.X0, y - y0])
                        {
                            if (x < cx0) cx0 = x;
                            if (x > cx1) cx1 = x;
                            if (y < cy0) cy0 = y;
                            if (y > cy1) cy1 = y;
                        }

                recortes.Add(limpio);
                cajas.Add(new int[] { cx0, cy0, cx1, cy1, s.X0, y0 });
                int w = cx1 - cx0 + 1, h = cy1 - cy0 + 1;
                if (w > maxAncho) maxAncho = w;
                if (h > maxAlto) maxAlto = h;
                log.AppendLine("   x " + s.X0 + "-" + s.X1 + "  contenido " + w + "x" + h);
            }
        }

        // --- Paso 2: lienzo cuadrado comun, iconos centrados a su tamano real ---
        int lado = Math.Max(maxAncho, maxAlto) + relleno * 2;
        log.AppendLine("Lienzo comun: " + lado + "x" + lado + " (mayor contenido " + maxAncho + "x" + maxAlto + ")");

        if (!System.IO.Directory.Exists(destino)) System.IO.Directory.CreateDirectory(destino);

        for (int i = 0; i < recortes.Count && i < nombres.Length; i++)
        {
            bool[,] limpio = recortes[i];
            int[] c = cajas[i];
            int cx0 = c[0], cy0 = c[1], cx1 = c[2], cy1 = c[3], baseX = c[4], baseY = c[5];
            int w = cx1 - cx0 + 1, h = cy1 - cy0 + 1;
            int offX = (lado - w) / 2, offY = (lado - h) / 2;

            Bitmap salida = new Bitmap(lado, lado, PixelFormat.Format32bppArgb);
            BitmapData ds = salida.LockBits(new Rectangle(0, 0, lado, lado), ImageLockMode.WriteOnly, PixelFormat.Format32bppArgb);
            int pasoS = ds.Stride;
            byte[] bytesS = new byte[pasoS * lado];

            for (int x = cx0; x <= cx1; x++)
                for (int y = cy0; y <= cy1; y++)
                {
                    if (!limpio[x - baseX, y - baseY]) continue;
                    int dx = offX + (x - cx0), dy = offY + (y - cy0);
                    int oS = dy * pasoS + dx * 4;
                    int oO = y * paso + x * 4;
                    bytesS[oS] = pixeles[oO];
                    bytesS[oS + 1] = pixeles[oO + 1];
                    bytesS[oS + 2] = pixeles[oO + 2];
                    bytesS[oS + 3] = pixeles[oO + 3];
                }

            System.Runtime.InteropServices.Marshal.Copy(bytesS, 0, ds.Scan0, bytesS.Length);
            salida.UnlockBits(ds);
            salida.Save(System.IO.Path.Combine(destino, nombres[i] + ".png"), ImageFormat.Png);
            salida.Dispose();
        }

        return log.ToString();
    }

    // Divide una fila en 10 segmentos: bloques con contenido, partiendo los que
    // contienen dos iconos pegados por su punto mas estrecho.
    static List<Segmento> SegmentarFila(int y0, int y1)
    {
        int[] perfil = new int[ancho];
        for (int x = 0; x < ancho; x++)
        {
            int c = 0;
            for (int y = y0; y <= y1; y++) if (mascara[x, y]) c++;
            perfil[x] = c;
        }

        List<Segmento> bloques = new List<Segmento>();
        bool dentro = false; int ini = 0;
        for (int x = 0; x < ancho; x++)
        {
            if (perfil[x] > 0 && !dentro) { dentro = true; ini = x; }
            else if (perfil[x] == 0 && dentro)
            {
                dentro = false;
                if (x - ini > 3) bloques.Add(new Segmento { X0 = ini, X1 = x - 1, CorteIzq = false, CorteDer = false });
            }
        }
        if (dentro && ancho - ini > 3) bloques.Add(new Segmento { X0 = ini, X1 = ancho - 1 });

        // Partir el bloque mas ancho hasta tener 10
        while (bloques.Count < 10)
        {
            int idx = 0, mejor = -1;
            for (int i = 0; i < bloques.Count; i++)
            {
                int w = bloques[i].X1 - bloques[i].X0;
                if (w > mejor) { mejor = w; idx = i; }
            }
            Segmento s = bloques[idx];
            int desde = s.X0 + (int)((s.X1 - s.X0) * 0.30);
            int hasta = s.X0 + (int)((s.X1 - s.X0) * 0.70);
            int corte = desde, minimo = int.MaxValue;
            for (int x = desde; x <= hasta; x++)
                if (perfil[x] < minimo) { minimo = perfil[x]; corte = x; }

            Segmento izq = new Segmento { X0 = s.X0, X1 = corte, CorteIzq = s.CorteIzq, CorteDer = true };
            Segmento der = new Segmento { X0 = corte + 1, X1 = s.X1, CorteIzq = true, CorteDer = s.CorteDer };
            bloques.RemoveAt(idx);
            bloques.Insert(idx, der);
            bloques.Insert(idx, izq);
        }

        return bloques;
    }

    // Etiquetado de regiones conectadas dentro del segmento; descarta las
    // pequenas que tocan un borde de corte (restos del icono vecino).
    static bool[,] ComponentesUtiles(Segmento s, int y0, int y1)
    {
        int w = s.X1 - s.X0 + 1, h = y1 - y0 + 1;
        int[,] etiqueta = new int[w, h];
        List<int> tamanos = new List<int>();
        List<bool> tocaCorte = new List<bool>();
        tamanos.Add(0); tocaCorte.Add(false); // etiqueta 0 = vacio

        int[] dx = { 1, -1, 0, 0, 1, 1, -1, -1 };
        int[] dy = { 0, 0, 1, -1, 1, -1, 1, -1 };

        for (int x = 0; x < w; x++)
            for (int y = 0; y < h; y++)
            {
                if (!mascara[s.X0 + x, y0 + y] || etiqueta[x, y] != 0) continue;
                int id = tamanos.Count;
                int cuenta = 0; bool toca = false;
                Stack<int> pila = new Stack<int>();
                pila.Push(x * h + y);
                etiqueta[x, y] = id;
                while (pila.Count > 0)
                {
                    int p = pila.Pop();
                    int px = p / h, py = p % h;
                    cuenta++;
                    if ((px == 0 && s.CorteIzq) || (px == w - 1 && s.CorteDer)) toca = true;
                    for (int k = 0; k < 8; k++)
                    {
                        int nx = px + dx[k], ny = py + dy[k];
                        if (nx < 0 || ny < 0 || nx >= w || ny >= h) continue;
                        if (etiqueta[nx, ny] != 0 || !mascara[s.X0 + nx, y0 + ny]) continue;
                        etiqueta[nx, ny] = id;
                        pila.Push(nx * h + ny);
                    }
                }
                tamanos.Add(cuenta); tocaCorte.Add(toca);
            }

        int mayor = 0;
        for (int i = 1; i < tamanos.Count; i++) if (tamanos[i] > tamanos[mayor]) mayor = i;

        bool[] conservar = new bool[tamanos.Count];
        for (int i = 1; i < tamanos.Count; i++)
        {
            if (i == mayor) { conservar[i] = true; continue; }
            // Restos del vecino: pequenos y pegados al borde por donde hemos cortado
            if (tocaCorte[i] && tamanos[i] < tamanos[mayor] * 0.40) { conservar[i] = false; continue; }
            // Piezas sueltas propias del icono (rodaja de naranja, hojas...)
            conservar[i] = tamanos[i] >= tamanos[mayor] * 0.02;
        }

        bool[,] limpio = new bool[w, h];
        for (int x = 0; x < w; x++)
            for (int y = 0; y < h; y++)
                limpio[x, y] = etiqueta[x, y] != 0 && conservar[etiqueta[x, y]];
        return limpio;
    }
}
'@

Add-Type -TypeDefinition $codigo -ReferencedAssemblies System.Drawing

$nombres = @(
  "01-bocata-embutido", "02-bocata-pollo", "03-bocata-lomo", "04-croasant-mixto", "05-croasant-vegetal",
  "06-sandwich-mixto", "07-sandwich-vegetal", "08-hamburguesa", "09-hamburguesa-bacon", "10-bocata-atun",
  "11-hot-dog", "12-frankfurt", "13-bocata-jamon", "14-bocata-vegetal", "15-bocata-tortilla",
  "16-bocata-salmon", "17-bocata-queso", "18-sandwich-club", "19-sandwich-pollo", "20-sandwich-atun",
  "21-bagel-salmon", "22-bagel-mixto", "23-bagel-vegetal", "24-muffin", "25-croasant-natural",
  "26-napolitana-chocolate", "27-napolitana-crema", "28-donut-chocolate", "29-donut-fresa", "30-donut-colores",
  "31-magdalena", "32-galleta-americana", "33-brownie", "34-tarta-queso", "35-tarta-chocolate",
  "36-tarta-zanahoria", "37-yogur-granola", "38-bowl-fruta", "39-ensalada-mixta", "40-ensalada-cesar",
  "41-zumo-naranja", "42-zumo-frutas", "43-batido-fresa", "44-batido-chocolate", "45-cafe-solo",
  "46-cafe-leche", "47-cortado", "48-cafe-americano", "49-te", "50-infusiones"
)

$bandas = @(@(222,334), @(390,518), @(589,700), @(778,905), @(969,1106))

$origen = "C:\Users\ferna\OneDrive\Documentos\RestauracionTony\pixelart.png"
$destino = "C:\Users\ferna\OneDrive\Documentos\RestauracionTony\assets\img\productos"

$log = [Recortador]::Procesar($origen, $destino, $nombres, $bandas, 8)
Write-Output $log
