import React, { useState, useEffect, useRef } from 'react';
import {
  StyleSheet,
  Text,
  View,
  ActivityIndicator,
  TextInput,
  StatusBar,
  Dimensions,
  PanResponder,
  FlatList,
  TouchableOpacity,
  ScrollView,
  Animated,
  Easing,
} from 'react-native';
import { WebView, WebViewMessageEvent } from 'react-native-webview';
import { Video, ResizeMode } from 'expo-av';

interface Canal {
  id: string;
  numero: number;
  name: string;
  url: string;
  logo: string;
  category: string;
}

const CANALES_MANUALES: Canal[] = [
  { id: 'man-1', numero: 1, name: 'DSports', url: 'https://streamtpday1.xyz/global1.php?stream=dsports', logo: 'https://upload.wikimedia.org/wikipedia/commons/d/df/DirecTV_Sports_logo.png', category: 'Deportes' },
  { id: 'man-2', numero: 2, name: 'DSports 2', url: 'https://streamtpday1.xyz/global1.php?stream=dsports2', logo: '', category: 'Deportes' },
  { id: 'man-3', numero: 3, name: 'DSports +', url: 'https://streamtpday1.xyz/global1.php?stream=dsportsplus', logo: '', category: 'Deportes' },
  { id: 'man-4', numero: 4, name: 'TyC Sports', url: 'https://streamtpday1.xyz/global1.php?stream=tycsports', logo: '', category: 'Deportes' },
  { id: 'man-5', numero: 5, name: 'TNT Sports', url: 'https://streamtpday1.xyz/global1.php?stream=tntsports', logo: '', category: 'Deportes' },
  { id: 'man-6', numero: 6, name: 'ESPN Premium', url: 'https://streamtpday1.xyz/global1.php?stream=espnpremium', logo: '', category: 'Deportes' },
  { id: 'man-7', numero: 7, name: 'ESPN 1', url: 'https://streamtpday1.xyz/global1.php?stream=espn', logo: '', category: 'Deportes' },
  { id: 'man-8', numero: 8, name: 'ESPN 2', url: 'https://streamtpday1.xyz/global1.php?stream=espn2', logo: '', category: 'Deportes' },
  { id: 'man-9', numero: 9, name: 'ESPN 3', url: 'https://streamtpday1.xyz/global1.php?stream=espn3', logo: '', category: 'Deportes' },
  { id: 'man-10', numero: 10, name: 'ESPN 4', url: 'https://streamtpday1.xyz/global1.php?stream=espn4', logo: '', category: 'Deportes' },
  { id: 'man-11', numero: 11, name: 'ESPN 5', url: 'https://streamtpday1.xyz/global1.php?stream=espn5', logo: '', category: 'Deportes' }
];

// Componente reproductor con expo-av + fallback a WebView
function ReproductorExpoAV({
  url,
  contentFit,
  onError,
}: {
  url: string;
  contentFit: 'contain' | 'fill';
  onError?: () => void;
}) {
  const videoRef = useRef<Video>(null);
  const [status, setStatus] = useState({});
  const [error, setError] = useState(false);

  useEffect(() => {
    if (videoRef.current && url) {
      videoRef.current.loadAsync({ uri: url }, {}, false);
    }
  }, [url]);

  const handleError = () => {
    setError(true);
    if (onError) onError();
  };

  if (error) {
    return (
      <View style={styles.fallbackContainer}>
        <Text style={styles.fallbackText}>Error en reproductor nativo</Text>
        <TouchableOpacity
          style={styles.fallbackButton}
          onPress={() => setError(false)}
        >
          <Text style={styles.fallbackButtonText}>Reintentar</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <Video
      ref={videoRef}
      style={StyleSheet.absoluteFill}
      resizeMode={contentFit === 'contain' ? ResizeMode.CONTAIN : ResizeMode.STRETCH}
      isLooping
      shouldPlay
      rate={1.0}
      volume={1.0}
      isMuted={false}
      onError={handleError}
      onPlaybackStatusUpdate={(s) => setStatus(s)}
      useNativeControls={false}
      // Android: forzar uso de MediaPlayer (más compatible con HLS en viejos)
      // En Expo Go no podemos pasar props adicionales, pero podemos probar con useNativeControls
    />
  );
}

export default function App() {
  const [listaCanales, setListaCanales] = useState<Canal[]>([]);
  const [canalSeleccionado, setCanalSeleccionado] = useState<Canal | null>(null);
  const [linkM3u8, setLinkM3u8] = useState<string | null>(null);
  const [cazando, setCazando] = useState(false);
  const [usarWebView, setUsarWebView] = useState(false); // Fallback

  const [mostrarSplash, setMostrarSplash] = useState(true);
  const splashOpacity = useRef(new Animated.Value(0)).current;
  const splashScale = useRef(new Animated.Value(0.92)).current;
  const splashTranslate = useRef(new Animated.Value(20)).current;
  const ringRotate = useRef(new Animated.Value(0)).current;
  const glowPulse = useRef(new Animated.Value(0.8)).current;
  const progressAnim = useRef(new Animated.Value(0)).current;

  const [categorias, setCategorias] = useState<string[]>(['Todos']);
  const [categoriaActiva, setCategoriaActiva] = useState('Todos');
  const [busqueda, setBusqueda] = useState('');
  const [mostrarMenu, setMostrarMenu] = useState(false);
  const [relacionAspecto, setRelacionAspecto] = useState<'Ajustar' | 'Estirar'>('Ajustar');

  const [numeroMarcado, setNumeroMarcado] = useState('');
  const [bannerInfoVisible, setBannerInfoVisible] = useState(false);
  const [errorCanal, setErrorCanal] = useState(false);

  const timerZapping = useRef<NodeJS.Timeout | null>(null);
  const timerBanner = useRef<NodeJS.Timeout | null>(null);
  const inputRef = useRef<TextInput>(null);

  const canalSeleccionadoRef = useRef<Canal | null>(null);
  const cargaEnCurso = useRef(false);

  const URL_M3U = 'https://naphdev.online/list.m3u';

  useEffect(() => {
    canalSeleccionadoRef.current = canalSeleccionado;
  }, [canalSeleccionado]);

  // Splash (más ligero)
  useEffect(() => {
    Animated.parallel([
      Animated.timing(splashOpacity, {
        toValue: 1,
        duration: 600,
        easing: Easing.out(Easing.cubic),
        useNativeDriver: true,
      }),
      Animated.timing(splashScale, {
        toValue: 1,
        duration: 600,
        easing: Easing.out(Easing.cubic),
        useNativeDriver: true,
      }),
      Animated.timing(splashTranslate, {
        toValue: 0,
        duration: 600,
        easing: Easing.out(Easing.cubic),
        useNativeDriver: true,
      }),
    ]).start();

    const rotacion = Animated.loop(
      Animated.timing(ringRotate, {
        toValue: 1,
        duration: 4500,
        easing: Easing.linear,
        useNativeDriver: true,
      })
    );
    rotacion.start();

    const glow = Animated.loop(
      Animated.sequence([
        Animated.timing(glowPulse, {
          toValue: 1.1,
          duration: 900,
          easing: Easing.inOut(Easing.quad),
          useNativeDriver: true,
        }),
        Animated.timing(glowPulse, {
          toValue: 0.85,
          duration: 900,
          easing: Easing.inOut(Easing.quad),
          useNativeDriver: true,
        }),
      ])
    );
    glow.start();

    Animated.timing(progressAnim, {
      toValue: 1,
      duration: 2500,
      easing: Easing.inOut(Easing.cubic),
      useNativeDriver: false,
    }).start();

    const t = setTimeout(() => {
      Animated.parallel([
        Animated.timing(splashOpacity, {
          toValue: 0,
          duration: 350,
          useNativeDriver: true,
        }),
        Animated.timing(splashScale, {
          toValue: 1.03,
          duration: 350,
          useNativeDriver: true,
        }),
      ]).start(() => {
        setMostrarSplash(false);
      });
    }, 2800);

    return () => {
      clearTimeout(t);
      rotacion.stop();
      glow.stop();
    };
  }, []);

  const cargarListaM3U = async (intento = 0) => {
    if (cargaEnCurso.current) return;
    cargaEnCurso.current = true;

    try {
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 10000);
      const res = await fetch(`${URL_M3U}?t=${Date.now()}`, {
        cache: 'no-store',
        signal: controller.signal,
      });
      clearTimeout(timeoutId);
      const data = await res.text();
      procesarListaM3U(data);
    } catch (error) {
      console.warn('Error cargando M3U:', error);
      if (intento < 3) {
        setTimeout(() => cargarListaM3U(intento + 1), 2000);
      } else {
        consolidarCanales([], new Set(['Todos']));
      }
    } finally {
      cargaEnCurso.current = false;
    }
  };

  useEffect(() => {
    cargarListaM3U();
    const intervalo = setInterval(() => cargarListaM3U(), 5000);
    return () => clearInterval(intervalo);
  }, []);

  const procesarListaM3U = (texto: string) => {
    const lineas = texto.split('\n');
    const canalesParseados: Canal[] = [];
    const juegoCategorias: Set<string> = new Set(['Todos', 'Deportes']);

    let infoTemporal = { name: '', logo: '', category: 'M3U Remotos' };
    let contadorId = 3000;
    let indexNumero = 12;

    lineas.forEach((linea) => {
      const limpiada = linea.trim();

      if (limpiada.startsWith('#EXTINF:')) {
        const partesComa = limpiada.split(',');
        const nombre = partesComa[partesComa.length - 1].trim() || 'Canal M3U';
        const matchLogo = limpiada.match(/tvg-logo="([^"]+)"/i);
        const logo = matchLogo ? matchLogo[1] : '';
        const matchGrupo = limpiada.match(/group-title="([^"]+)"/i);
        const category = matchGrupo ? matchGrupo[1] : 'General';

        infoTemporal = { name: nombre, logo, category };
        juegoCategorias.add(category);
      } else if (limpiada.startsWith('http')) {
        contadorId++;
        canalesParseados.push({
          id: String(contadorId),
          numero: indexNumero++,
          name: infoTemporal.name || 'Canal Remoto',
          logo: infoTemporal.logo,
          category: infoTemporal.category,
          url: limpiada
        });
        infoTemporal = { name: '', logo: '', category: 'M3U Remotos' };
      }
    });

    consolidarCanales(canalesParseados, juegoCategorias);
  };

  const consolidarCanales = (remotos: Canal[], mapaCategorias: Set<string>) => {
    const mezclaCompleta = [...CANALES_MANUALES, ...remotos];
    const actual = canalSeleccionadoRef.current;

    setListaCanales(mezclaCompleta);
    setCategorias(Array.from(mapaCategorias));

    if (actual) {
      const coincidencia = mezclaCompleta.find(
        (c) =>
          c.id === actual.id ||
          (c.name === actual.name && c.category === actual.category)
      );
      if (coincidencia) {
        if (coincidencia.url !== actual.url) {
          sintonizarCanalFijo(coincidencia);
        }
        return;
      }
    }

    if (!actual && mezclaCompleta.length > 0) {
      sintonizarCanalFijo(mezclaCompleta[0]);
    }
  };

  const canalSiguiente = () => {
    if (!canalSeleccionado || listaCanales.length === 0) return;
    const indexActual = listaCanales.findIndex(c => c.id === canalSeleccionado.id);
    const siguienteIndex = (indexActual + 1) % listaCanales.length;
    sintonizarCanalFijo(listaCanales[siguienteIndex]);
  };

  const canalAnterior = () => {
    if (!canalSeleccionado || listaCanales.length === 0) return;
    const indexActual = listaCanales.findIndex(c => c.id === canalSeleccionado.id);
    const anteriorIndex = indexActual === 0 ? listaCanales.length - 1 : indexActual - 1;
    sintonizarCanalFijo(listaCanales[anteriorIndex]);
  };

  const gestosPantalla = useRef(
    PanResponder.create({
      onStartShouldSetPanResponder: () => true,
      onPanResponderRelease: (e, gestureState) => {
        const { x0, dx, dy } = gestureState;
        const anchoPantalla = Dimensions.get('window').width;

        if (Math.abs(dx) < 15 && Math.abs(dy) < 15) {
          if (x0 > anchoPantalla * 0.75) {
            canalSiguiente();
          } else if (x0 < anchoPantalla * 0.25) {
            canalAnterior();
          } else {
            setMostrarMenu(!mostrarMenu);
          }
        } else if (Math.abs(dy) > 40) {
          if (x0 > anchoPantalla * 0.5) {
            dy < 0 ? canalSiguiente() : canalAnterior();
          } else {
            dy < 0 ? canalSiguiente() : canalAnterior();
          }
        }
      }
    })
  ).current;

  const alEscribirControlRemoto = (texto: string) => {
    const limpio = texto.replace(/[^0-9]/g, '');
    if (!limpio) return;

    setNumeroMarcado(limpio);

    if (timerZapping.current) clearTimeout(timerZapping.current);

    timerZapping.current = setTimeout(() => {
      const encontrado = listaCanales.find(c => c.numero === parseInt(limpio));
      if (encontrado) sintonizarCanalFijo(encontrado);
      else {
        setErrorCanal(true);
        setTimeout(() => setErrorCanal(false), 2000);
      }
      setNumeroMarcado('');
    }, 1400);
  };

  const sintonizarCanalFijo = (canal: Canal) => {
    setLinkM3u8(null);
    setCanalSeleccionado(canal);
    setBannerInfoVisible(true);
    setUsarWebView(false); // Resetear fallback

    if (timerBanner.current) clearTimeout(timerBanner.current);
    timerBanner.current = setTimeout(() => setBannerInfoVisible(false), 3000);

    if (canal.url.toLowerCase().includes('.m3u8') || canal.url.toLowerCase().includes('.mpd')) {
      setLinkM3u8(canal.url);
      setCazando(false);
    } else {
      setCazando(true);
    }
  };

  const alRecibirMensajeWeb = (event: WebViewMessageEvent) => {
    if (event.nativeEvent.data.includes('.m3u8') || event.nativeEvent.data.includes('.mpd')) {
      setLinkM3u8(event.nativeEvent.data);
      setCazando(false);
      setUsarWebView(false);
    }
  };

  const canalesFiltrados = listaCanales.filter(c => {
    const matchCat = categoriaActiva === 'Todos' || c.category === categoriaActiva;
    const matchBusq = c.name.toLowerCase().includes(busqueda.toLowerCase());
    return matchCat && matchBusq;
  });

  const contentFit: 'contain' | 'fill' =
    relacionAspecto === 'Ajustar' ? 'contain' : 'fill';

  const ringSpin = ringRotate.interpolate({
    inputRange: [0, 1],
    outputRange: ['0deg', '360deg'],
  });

  const progressWidth = progressAnim.interpolate({
    inputRange: [0, 1],
    outputRange: ['0%', '100%'],
  });

  if (mostrarSplash) {
    return (
      <View style={styles.splashContainer}>
        <StatusBar hidden />
        <Animated.View
          style={[
            styles.splashCenterWrap,
            {
              opacity: splashOpacity,
              transform: [
                { scale: splashScale },
                { translateY: splashTranslate },
              ],
            },
          ]}
        >
          <Animated.View
            style={[
              styles.logoGlow,
              {
                transform: [{ scale: glowPulse }],
              },
            ]}
          />

          <Animated.View
            style={[
              styles.logoOuterRing,
              {
                transform: [{ rotate: ringSpin }],
              },
            ]}
          >
            <View style={styles.logoRingAccentTop} />
            <View style={styles.logoRingAccentBottom} />
            <View style={styles.logoRingAccentLeft} />
            <View style={styles.logoRingAccentRight} />
          </Animated.View>

          <View style={styles.logoCore}>
            <View style={styles.logoInnerFrame}>
              <View style={styles.logoNWrap}>
                <View style={styles.logoNBarLeft} />
                <View style={styles.logoNBarRight} />
                <View style={styles.logoNDiagonal} />
              </View>
            </View>
          </View>

          <Text style={styles.splashTitle}>NEXUS TV</Text>
          <Text style={styles.splashSub}>NEXUS STREAM PLATFORM</Text>

          <View style={styles.progressTrack}>
            <Animated.View style={[styles.progressFill, { width: progressWidth }]} />
          </View>

          <Text style={styles.loadingText}>CARGANDO CANALES...</Text>
        </Animated.View>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <StatusBar hidden />

      <TextInput
        ref={inputRef}
        value={numeroMarcado}
        onChangeText={alEscribirControlRemoto}
        keyboardType="numeric"
        showSoftInputOnFocus={false}
        style={styles.inputOculto}
      />

      <View style={StyleSheet.absoluteFill} {...gestosPantalla.panHandlers}>
        {linkM3u8 && !usarWebView ? (
          <ReproductorExpoAV
            key={linkM3u8}
            url={linkM3u8}
            contentFit={contentFit}
            onError={() => {
              // Si falla el reproductor nativo, intentar con WebView
              setUsarWebView(true);
            }}
          />
        ) : linkM3u8 && usarWebView ? (
          <WebView
            source={{
              html: `
                <!DOCTYPE html>
                <html>
                  <head>
                    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
                    <script src="https://cdn.jsdelivr.net/npm/hls.js@0.14.17/dist/hls.min.js"></script>
                    <style>
                      body { margin: 0; background: #000; display: flex; justify-content: center; align-items: center; height: 100vh; }
                      video { width: 100%; height: 100%; background: #000; }
                    </style>
                  </head>
                  <body>
                    <video id="video" controls autoplay></video>
                    <script>
                      if (Hls.isSupported()) {
                        var video = document.getElementById('video');
                        var hls = new Hls();
                        hls.loadSource('${linkM3u8}');
                        hls.attachMedia(video);
                        hls.on(Hls.Events.MANIFEST_PARSED, function() { video.play(); });
                      } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                        video.src = '${linkM3u8}';
                        video.addEventListener('loadedmetadata', function() { video.play(); });
                      }
                    </script>
                  </body>
                </html>
              `
            }}
            style={StyleSheet.absoluteFill}
            javaScriptEnabled
            domStorageEnabled
            mediaPlaybackRequiresUserAction={false}
            allowsInlineMediaPlayback
          />
        ) : canalSeleccionado ? (
          <View style={styles.loaderFondo}>
            <ActivityIndicator size="large" color="#ffffff" />
            <Text style={styles.textCarga}>
              SINTONIZANDO CH {canalSeleccionado.numero}
            </Text>
            <Text style={styles.subtextCarga}>{canalSeleccionado.name}</Text>
          </View>
        ) : null}
      </View>

      {numeroMarcado !== '' && (
        <View style={styles.osdNumero}>
          <Text style={styles.osdNumeroTexto}>{numeroMarcado}</Text>
        </View>
      )}

      {errorCanal && (
        <View style={styles.osdError}>
          <Text style={styles.osdErrorTexto}>CANAL NO ENCONTRADO</Text>
        </View>
      )}

      {bannerInfoVisible && canalSeleccionado && !mostrarMenu && (
        <View style={styles.miniBanner}>
          <Text style={styles.miniBannerCh}>CH {canalSeleccionado.numero}</Text>
          <Text style={styles.miniBannerName}>{canalSeleccionado.name}</Text>
        </View>
      )}

      {mostrarMenu && (
        <View style={styles.overlayMenuGlobal}>
          <View style={styles.menuLateral}>
            <Text style={styles.tituloMenu}>
              NEXUS <Text style={{ color: '#6366f1' }}>TV</Text>
            </Text>

            <TextInput
              style={styles.buscadorMenu}
              placeholder="🔍 Buscar..."
              placeholderTextColor="#555577"
              value={busqueda}
              onChangeText={setBusqueda}
            />

            <Text style={styles.seccionLabel}>CATEGORÍAS</Text>
            <ScrollView style={{ flex: 1 }}>
              {categorias.map(cat => (
                <TouchableOpacity
                  key={cat}
                  style={[
                    styles.btnCategoria,
                    categoriaActiva === cat && styles.btnCategoriaActivo
                  ]}
                  onPress={() => setCategoriaActiva(cat)}
                >
                  <Text
                    style={[
                      styles.txtCategoria,
                      categoriaActiva === cat && { color: '#fff' }
                    ]}
                    numberOfLines={1}
                  >
                    {cat}
                  </Text>
                </TouchableOpacity>
              ))}
            </ScrollView>

            <Text style={styles.seccionLabel}>PANTALLA</Text>
            <TouchableOpacity
              style={styles.btnAjuste}
              onPress={() =>
                setRelacionAspecto(
                  relacionAspecto === 'Ajustar' ? 'Estirar' : 'Ajustar'
                )
              }
            >
              <Text style={styles.txtAjuste}>Aspecto: {relacionAspecto}</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[
                styles.btnAjuste,
                { borderColor: '#ef4444', marginTop: 5 }
              ]}
              onPress={() => setMostrarMenu(false)}
            >
              <Text style={[styles.txtAjuste, { color: '#ef4444' }]}>
                Cerrar Menú ✕
              </Text>
            </TouchableOpacity>
          </View>

          <View style={styles.grillaCanalesContenedor}>
            <FlatList
              data={canalesFiltrados}
              keyExtractor={(item) => item.id}
              numColumns={2}
              windowSize={5}
              maxToRenderPerBatch={6}
              initialNumToRender={4}
              removeClippedSubviews={true}
              renderItem={({ item }) => {
                const activo = canalSeleccionado?.id === item.id;
                return (
                  <TouchableOpacity
                    style={[styles.cardCanal, activo && styles.cardCanalActivo]}
                    onPress={() => {
                      sintonizarCanalFijo(item);
                      setMostrarMenu(false);
                    }}
                  >
                    <Text style={styles.cardNumero}>CH {item.numero}</Text>
                    <Text
                      style={[
                        styles.cardNombre,
                        activo && { color: '#fff', fontWeight: 'bold' }
                      ]}
                      numberOfLines={1}
                    >
                      {item.name}
                    </Text>
                  </TouchableOpacity>
                );
              }}
            />
          </View>
        </View>
      )}

      {cazando && canalSeleccionado && (
        <View style={{ width: 0, height: 0, opacity: 0 }}>
          <WebView
            source={{ uri: canalSeleccionado.url }}
            injectedJavaScriptBeforeContentLoaded={`
              (function() {
                const originalOpen = XMLHttpRequest.prototype.open;
                XMLHttpRequest.prototype.open = function(method, url) {
                  if (url && (url.includes('.m3u8') || url.includes('.mpd'))) {
                    window.ReactNativeWebView.postMessage(url);
                  }
                  return originalOpen.apply(this, arguments);
                };

                const originalFetch = window.fetch;
                window.fetch = function(input, init) {
                  const url = typeof input === 'string' ? input : input.url;
                  if (url && (url.includes('.m3u8') || url.includes('.mpd'))) {
                    window.ReactNativeWebView.postMessage(url);
                  }
                  return originalFetch.call(this, input, init);
                };

                const observer = new MutationObserver(() => {
                  const links = document.querySelectorAll('a[href*=".m3u8"], video source[src*=".m3u8"]');
                  links.forEach(el => {
                    const href = el.href || el.src;
                    if (href && (href.includes('.m3u8') || href.includes('.mpd'))) {
                      window.ReactNativeWebView.postMessage(href);
                    }
                  });
                });
                observer.observe(document.body, { childList: true, subtree: true });
              })();
              true;
            `}
            onMessage={alRecibirMensajeWeb}
            javaScriptEnabled={true}
            domStorageEnabled={true}
            mediaPlaybackRequiresUserAction={false}
            mixedContentMode="always"
            allowFileAccess={true}
            allowUniversalAccessFromFileURLs={true}
            allowFileAccessFromFileURLs={true}
            androidLayerType="software"
            startInLoadingState={true}
          />
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#000' },
  inputOculto: { position: 'absolute', top: -100, left: -100, opacity: 0 },

  splashContainer: {
    flex: 1,
    backgroundColor: '#05060b',
    justifyContent: 'center',
    alignItems: 'center',
    overflow: 'hidden',
  },
  splashCenterWrap: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  logoGlow: {
    position: 'absolute',
    top: -18,
    width: 180,
    height: 180,
    borderRadius: 90,
    backgroundColor: 'rgba(99,102,241,0.14)',
  },
  logoOuterRing: {
    width: 148,
    height: 148,
    borderRadius: 74,
    borderWidth: 1.5,
    borderColor: 'rgba(129,140,248,0.55)',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: -132,
  },
  logoRingAccentTop: {
    position: 'absolute',
    top: -1,
    width: 34,
    height: 5,
    borderRadius: 8,
    backgroundColor: '#818cf8',
  },
  logoRingAccentBottom: {
    position: 'absolute',
    bottom: -1,
    width: 26,
    height: 5,
    borderRadius: 8,
    backgroundColor: '#6366f1',
  },
  logoRingAccentLeft: {
    position: 'absolute',
    left: -1,
    width: 5,
    height: 26,
    borderRadius: 8,
    backgroundColor: '#818cf8',
  },
  logoRingAccentRight: {
    position: 'absolute',
    right: -1,
    width: 5,
    height: 26,
    borderRadius: 8,
    backgroundColor: '#6366f1',
  },
  logoCore: {
    width: 118,
    height: 118,
    borderRadius: 30,
    backgroundColor: '#0d1020',
    borderWidth: 1,
    borderColor: 'rgba(99,102,241,0.45)',
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: '#6366f1',
    shadowOpacity: 0.35,
    shadowRadius: 18,
    shadowOffset: { width: 0, height: 0 },
    elevation: 10,
  },
  logoInnerFrame: {
    width: 92,
    height: 92,
    borderRadius: 24,
    backgroundColor: '#0a0d18',
    borderWidth: 1,
    borderColor: 'rgba(129,140,248,0.22)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  logoNWrap: {
    width: 52,
    height: 52,
    position: 'relative',
  },
  logoNBarLeft: {
    position: 'absolute',
    left: 0,
    top: 0,
    width: 10,
    height: 52,
    borderRadius: 10,
    backgroundColor: '#ffffff',
  },
  logoNBarRight: {
    position: 'absolute',
    right: 0,
    top: 0,
    width: 10,
    height: 52,
    borderRadius: 10,
    backgroundColor: '#ffffff',
  },
  logoNDiagonal: {
    position: 'absolute',
    left: 21,
    top: -3,
    width: 10,
    height: 58,
    borderRadius: 10,
    backgroundColor: '#6366f1',
    transform: [{ rotate: '-32deg' }],
    shadowColor: '#6366f1',
    shadowOpacity: 0.45,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 0 },
  },
  splashTitle: {
    marginTop: 26,
    color: '#fff',
    fontSize: 34,
    fontWeight: '900',
    letterSpacing: 4,
  },
  splashSub: {
    marginTop: 8,
    color: '#8f92b3',
    fontSize: 11,
    letterSpacing: 2.6,
    fontWeight: '700',
  },
  progressTrack: {
    width: 220,
    height: 6,
    borderRadius: 999,
    backgroundColor: '#15182a',
    marginTop: 26,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: '#1f2440',
  },
  progressFill: {
    height: '100%',
    borderRadius: 999,
    backgroundColor: '#6366f1',
  },
  loadingText: {
    marginTop: 12,
    color: '#6366f1',
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 2,
  },

  loaderFondo: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: '#06070c',
    justifyContent: 'center',
    alignItems: 'center'
  },
  textCarga: {
    color: '#fff',
    fontSize: 16,
    fontWeight: 'bold',
    marginTop: 15,
    letterSpacing: 1
  },
  subtextCarga: {
    color: '#6366f1',
    fontSize: 13,
    marginTop: 4
  },

  osdNumero: {
    position: 'absolute',
    top: 40,
    right: 40,
    backgroundColor: 'rgba(0,0,0,0.85)',
    paddingHorizontal: 20,
    paddingVertical: 5,
    borderRadius: 6,
    borderWidth: 2,
    borderColor: '#6366f1'
  },
  osdNumeroTexto: {
    color: '#6366f1',
    fontSize: 38,
    fontWeight: '900',
    fontFamily: 'monospace'
  },
  osdError: {
    position: 'absolute',
    top: 40,
    right: 40,
    backgroundColor: '#ef4444',
    paddingHorizontal: 15,
    paddingVertical: 8,
    borderRadius: 6
  },
  osdErrorTexto: {
    color: '#fff',
    fontSize: 12,
    fontWeight: 'bold'
  },

  miniBanner: {
    position: 'absolute',
    bottom: 30,
    left: 30,
    backgroundColor: 'rgba(0,0,0,0.8)',
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 8,
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#1f2033'
  },
  miniBannerCh: {
    color: '#6366f1',
    fontWeight: '900',
    fontSize: 16,
    marginRight: 10,
    fontFamily: 'monospace'
  },
  miniBannerName: {
    color: '#fff',
    fontWeight: 'bold',
    fontSize: 15
  },

  overlayMenuGlobal: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(5, 6, 10, 0.85)',
    flexDirection: 'row',
    zIndex: 99999
  },

  menuLateral: {
    width: '35%',
    backgroundColor: '#0c0d14',
    borderRightWidth: 1,
    borderColor: '#1e2030',
    padding: 15,
    justifyContent: 'space-between'
  },
  tituloMenu: {
    fontSize: 18,
    color: '#fff',
    fontWeight: '900',
    letterSpacing: 1,
    marginBottom: 12,
    textAlign: 'center'
  },
  buscadorMenu: {
    backgroundColor: '#161722',
    color: '#fff',
    borderRadius: 8,
    paddingHorizontal: 10,
    paddingVertical: 5,
    fontSize: 12,
    marginBottom: 15,
    borderWidth: 1,
    borderColor: '#25273c'
  },
  seccionLabel: {
    color: '#4e5073',
    fontSize: 10,
    fontWeight: 'bold',
    letterSpacing: 1,
    marginBottom: 8,
    marginTop: 5
  },
  btnCategoria: {
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 6,
    marginBottom: 4
  },
  btnCategoriaActivo: {
    backgroundColor: '#6366f1'
  },
  txtCategoria: {
    color: '#8f92b3',
    fontSize: 12,
    fontWeight: '600'
  },
  btnAjuste: {
    borderWidth: 1,
    borderColor: '#2e314d',
    padding: 8,
    borderRadius: 6,
    alignItems: 'center'
  },
  txtAjuste: {
    color: '#a3a6cc',
    fontSize: 11,
    fontWeight: 'bold'
  },

  grillaCanalesContenedor: {
    width: '65%',
    padding: 10,
    justifyContent: 'center'
  },
  cardCanal: {
    flex: 1 / 2,
    backgroundColor: '#12131f',
    margin: 4,
    padding: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#1e2035',
    height: 65,
    justifyContent: 'center'
  },
  cardCanalActivo: {
    borderColor: '#6366f1',
    backgroundColor: 'rgba(99, 102, 241, 0.15)'
  },
  cardNumero: {
    color: '#6366f1',
    fontSize: 10,
    fontWeight: 'bold',
    fontFamily: 'monospace',
    marginBottom: 2
  },
  cardNombre: {
    color: '#8f92b3',
    fontSize: 12,
    fontWeight: '500'
  },

  fallbackContainer: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: '#111',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  fallbackText: {
    color: '#fff',
    fontSize: 16,
    marginBottom: 20,
  },
  fallbackButton: {
    backgroundColor: '#6366f1',
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 8,
  },
  fallbackButtonText: {
    color: '#fff',
    fontWeight: 'bold',
  },
});
