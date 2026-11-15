# BAB Shop mobile

A small Flutter app that opens the PHP checkout in a WebView, so the same payment flow runs inside a mobile app. The Gate-E payment page loads in the page's own iframe, and the receipt page replaces it after payment.

What the app does:

- Loads the checkout and shows a progress bar and an offline error screen with a retry button.
- Only opens the shop host and `*.gate-e.net`; any other link is blocked (`lib/url_policy.dart`, covered by unit tests).
- The Android back button goes back inside the web page before it leaves the app.

## Run

```bash
cd mobile
flutter pub get

# Android emulator, with `php -S localhost:8000 -t public` running on the PC
flutter run

# A real phone, using the public tunnel address
flutter run --dart-define=CHECKOUT_URL=https://your-tunnel.trycloudflare.com/
```

The default address is `http://10.0.2.2:8000/`, which is how an Android emulator reaches the PC. Plain http is allowed in debug builds only (`android/app/src/debug/AndroidManifest.xml`); a release build needs an https address.

```bash
flutter test      # URL policy tests
flutter analyze
flutter build apk --release --dart-define=CHECKOUT_URL=https://your-domain/
```

Accept the Android licences once with `flutter doctor --android-licenses` before building.

## Run in Chrome

The web build shows the checkout in an iframe and supports `loadRequest` only. For local development, add `http://localhost:5000` to the `frame-ancestors` list in `sendSecurityHeaders()` (`bootstrap.php`) so the shop may be framed, then run:

```bash
flutter run -d chrome --web-port 5000
```
