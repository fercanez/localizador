# Generar APK de prueba (Android)

La app usa Capacitor y abre el sitio real:

`https://localizacion.geomexicali.info/login.php`

## Requisitos en tu PC

1. [Node.js LTS](https://nodejs.org/) (incluye npm)
2. [Android Studio](https://developer.android.com/studio)
3. JDK 17 (Android Studio suele instalarlo)

## Pasos

En PowerShell:

```powershell
cd d:\geomexicali\localizacion\mobile
npm install
npx cap add android
npx cap sync android
npx cap open android
```

En Android Studio:

1. Espere a que termine Gradle Sync
2. Menú **Build → Build Bundle(s) / APK(s) → Build APK(s)**
3. El APK queda en algo como:

`mobile\android\app\build\outputs\apk\debug\app-debug.apk`

4. Copie el APK al teléfono/tableta e instálelo (permitir fuentes desconocidas si hace falta)

## Prueba

1. Abra la app
2. Inicie sesión con `admin` / la contraseña creada por `scripts/seed_admin.php`
3. Debe verse el mapa; si es admin, el botón **Usuarios**

## Ícono de la app (Escudo de Mexicali)

La fuente del ícono está en:

- `mobile/resources/icon.png` (escudo municipal, cuadrado 512)
- También en el sitio: `escudo-mexicali.png`, `assets/icons/icon-192.png`, `assets/icons/icon-512.png`

Después de `npx cap add android`, genera los tamaños Android automáticamente:

```powershell
cd d:\geomexicali\localizacion\mobile
npm install @capacitor/assets --save-dev
npx capacitor-assets generate --android
npx cap sync android
```

Luego vuelve a compilar el APK en Android Studio.
