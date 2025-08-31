// LAZY LOAD images with fallback
// Lazy Load images.  Make sure image as data-src, data-srcset present, and loading="lazy". Remove src attribute

function lazyLoadImages() {

  if ('IntersectionObserver' in window) {

    const images = document.querySelectorAll('img[data-src][loading="lazy"]:not(.loaded)');
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const image = entry.target;

                image.src = image.dataset.src;
                if ( image.dataset.srcset ) {
                  image.srcset = image.dataset.srcset;
                }

                image.onload = () => {
                  if ( image.classList.contains('opacity-0' ) ) {
                    image.classList.add('!opacity-100');
                  }
                }

                imageObserver.unobserve(image);

                image.classList.add('loaded');
            }
        });
    });

    images.forEach(img => imageObserver.observe(img));

  } else {

    if ('loading' in HTMLImageElement.prototype) {
      const images = document.querySelectorAll('img[data-src][loading="lazy"]');
      images.forEach(img => {
          img.src = img.dataset.src;
          if ( img.dataset.srcset ) {
              img.srcset = img.dataset.srcset;
          }
      });
    } else {
      // Dynamically import the LazySizes library
      const script = document.createElement('script');
      script.src =
          'https://cdnjs.cloudflare.com/ajax/libs/lazysizes/5.1.2/lazysizes.min.js';
      document.body.appendChild(script);
    }

  }
}

function supportsHEVCAlpha() {
  const navigator = window.navigator;
  const ua = navigator.userAgent.toLowerCase()
  const hasMediaCapabilities = !!(navigator.mediaCapabilities && navigator.mediaCapabilities.decodingInfo)
  const isSafari = ((ua.indexOf('safari') != -1) && (!(ua.indexOf('chrome')!= -1) && (ua.indexOf('version/')!= -1)))
  return isSafari && hasMediaCapabilities
}

function lazyLoadVideos() {
  var lazyVideos = [].slice.call(document.querySelectorAll("video.lazy"));

  if ("IntersectionObserver" in window) {
    var lazyVideoObserver = new IntersectionObserver(function(entries, observer) {
      entries.forEach(function(video) {
        if (video.isIntersecting) {
          
          for (var source in video.target.children) {
            var videoSource = video.target.children[source];
            if (typeof videoSource.tagName === "string" && videoSource.tagName === "SOURCE") {
              if ( supportsHEVCAlpha() && videoSource.dataset.srcmov) {
                videoSource.src = videoSource.dataset.srcmov;
              } else {
                videoSource.src = videoSource.dataset.src;
              }
            }
          }
          
          video.target.load();
          
          video.target.addEventListener('timeupdate', (event) => {
            setTimeout(function() {
              video.target.classList.remove("lazy", "opacity-0");
            }, 500);
          });
          
          lazyVideoObserver.unobserve(video.target);
        }
      });
    });

    lazyVideos.forEach(function(lazyVideo) {
      lazyVideoObserver.observe(lazyVideo);
    });
  }
}

function dynLoadVideos() {
  var lazyVideos = [].slice.call(document.querySelectorAll("video.eager"));
  if (lazyVideos.length) {
    lazyVideos.forEach(function(video) {
          
          for (var source in video.children) {
            var videoSource = video.children[source];
            if (typeof videoSource.tagName === "string" && videoSource.tagName === "SOURCE") {
              if ( supportsHEVCAlpha() && videoSource.dataset.srcmov) {
                videoSource.src = videoSource.dataset.srcmov;
              } else {
                videoSource.src = videoSource.dataset.src;
              }
            }
          }
          
          video.load();
          
          video.addEventListener('timeupdate', (event) => {
            setTimeout(function() {
              video.classList.remove("eager", "opacity-0");
            }, 500);
          });
    });

  }
}

// safari doesn't load srcset images after ajax
function resetSrcset() {
  let ee = document.querySelectorAll('[loading="eager"]:not(.loaded)');
  if ( ee.length ) {
      ee.forEach( (e) => {
          let srcset = e.srcset;
          e.srcset = '';
          e.srcset = srcset;
          e.classList.add('loaded');
      })
  }
}

document.addEventListener('DOMContentLoaded', () => {
  lazyLoadImages();
  lazyLoadVideos();
  dynLoadVideos();
});