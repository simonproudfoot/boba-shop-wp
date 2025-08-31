/**
 * Required plugins
 */
const child_process = require('child_process');

const gulp = require("gulp"),

  exec = require("child_process").default,
  uglify = require("gulp-uglify-es").default,
  sass = require("gulp-sass")(require("sass")),
  babel = require("gulp-babel"),
  plumber = require("gulp-plumber"),
  cache = require("gulp-cached"),
  rename = require("gulp-rename"),
  autoprefixer = require("gulp-autoprefixer"),
  notify = require("gulp-notify"),
  imagemin = require("gulp-imagemin"),
  clean = require("gulp-clean"),
  sourcemaps = require("gulp-sourcemaps"),
  runSequence = require("gulp4-run-sequence"),
  concat = require("gulp-concat"),
  gracefulfs = require("graceful-fs"),
  browserSync = require("browser-sync").create();


require('dotenv').config();

const tailwindcss = require("tailwindcss"),
  postcss = require("gulp-postcss"),
  purgecss = require("gulp-purgecss"),
  cleanCSS = require("gulp-clean-css");



/**
 * Paths
 */
const base = {
  src: "develop",
  public: "wp-content/themes/boba_shop",
  browserSyncProxy: 'http://localhost:10014/',
},
  paths = {
    src: {
      root: base.src,
      js: {
        site: base.src + "/js/site/",
        lib: base.src + "/js/lib/",
      },
      css: base.src + "/sass/",
      assets: base.src + "/assets/",
      acfjson: base.src + "/acf-json/",
      screenshot: base.src + "/screenshot.png",
    },
    public: {
      root: base.public,
      js: base.public + "/scripts/",
      css: base.public,
      imgs: base.public + "/assets/images/",
      assets: base.public + "/assets/",
      acfjson: base.public + "/acf-json/",
      screenshot: base.public,
    },
  };

/**
 * Clean public folder
 */
gulp.task(
  "clean",
  gulp.series(function (done) {
    return gulp
      .src(paths.public.root, { allowEmpty: true }, { allowEmpty: true })
      .pipe(clean({ force: true }));

    done();
  })
);

/**
 * Clean ACF-JSON folder
 */
gulp.task(
  "clean-acf-json",
  gulp.series(function (done) {
    return gulp
      .src(paths.public.acfjson + "/*", { allowEmpty: true })
      .pipe(clean({ force: true }));

    done();
  })
);

/**
 * Styles development
 */
gulp.task(
  "styles.dev",
  gulp.series(function () {
    return gulp
      .src(paths.src.css + "style.scss", { allowEmpty: true })
      .pipe(
        sass({
          outputStyle: "compressed",
          includePaths: ["node_modules"],
        }).on("error", sass.logError)
      )
      .pipe(
        postcss([require("autoprefixer"), tailwindcss("tailwind.config.js")])
      )
      .pipe(sourcemaps.write("."))
      .pipe(gulp.dest(paths.public.css));
  })
);

gulp.task(
  "styles-acfe.dev",
  gulp.series(function (done) {
    return gulp
      .src(paths.src.css + "/acfe/acfe-style.scss", { allowEmpty: true })
      .pipe(
        sass({
          outputStyle: "compressed",
          includePaths: ["node_modules"],
        }).on("error", sass.logError)
      )
      .pipe(
        postcss([
          require("autoprefixer"),
          tailwindcss("tailwind-acfe.config.js"),
        ])
      )
      .pipe(sourcemaps.write("."))
      .pipe(gulp.dest(paths.public.css));
    done();
  })
);

/**
 * Styles public
 */
gulp.task(
  "styles.public",
  gulp.series(function () {
    return gulp
      .src(paths.src.css + "style.scss", { allowEmpty: true })
      .pipe(
        sass({ includePaths: ["node_modules"] }).on("error", sass.logError)
      )
      .pipe(
        postcss([require("autoprefixer"), tailwindcss("tailwind.config.js")])
      )
      .pipe(cleanCSS({ level: 2 }))
      .pipe(sourcemaps.write("."))
      .pipe(gulp.dest(paths.public.css));
  })
);

/**
 * Clean Styles Development folder
 */
gulp.task(
  "clean.dev.styles",
  gulp.series(function (done) {
    return gulp
      .src(paths.src.css + "*.css", { allowEmpty: true }, { allowEmpty: true })
      .pipe(clean({ force: true }));

    done();
  })
);

/**
 * Lib scripts development
 */
gulp.task(
  "scripts.lib.dev",
  gulp.series(function (done) {
    gulp
      .src(paths.src.js.lib + "*.js", { allowEmpty: true })
      .pipe(sourcemaps.init())
      .pipe(plumber())
      .pipe(rename({ suffix: ".min" }))
      .on("error", notify.onError())
      .pipe(sourcemaps.write("."))
      .pipe(gulp.dest(paths.public.js + "lib/"));

    done();
  })
);

/**
 * Main scripts development
 */
gulp.task(
  "scripts.main.dev",
  gulp.series(function (done) {
    gulp
      .src(paths.src.js.site + "*.js", { allowEmpty: true })
      .pipe(sourcemaps.init())
      .pipe(plumber())
      .pipe(babel())
      .pipe(rename({ suffix: ".min" }))
      .on("error", notify.onError())
      .pipe(sourcemaps.write("."))
      .pipe(gulp.dest(paths.public.js + "site/"));

    done();
  })
);

/**
 * Lib scripts build for public
 */
gulp.task(
  "scripts.lib.public",
  gulp.series(function (done) {
    gulp
      .src([paths.src.js.lib + "*.js"], { allowEmpty: true })
      .pipe(plumber())
      .pipe(rename({ suffix: ".min" }))
      .pipe(uglify())
      .on("error", notify.onError())
      .pipe(gulp.dest(paths.public.js + "lib/"));

    done();
  })
);

/**
 * Main scripts build for public
 */
gulp.task(
  "scripts.main.public",
  gulp.series(function (done) {
    gulp
      .src([paths.src.js.site + "*.js"], { allowEmpty: true })
      .pipe(plumber())
      .pipe(babel())
      .pipe(rename({ suffix: ".min" }))
      .pipe(uglify())
      .on("error", notify.onError())
      .pipe(gulp.dest(paths.public.js + "site/"));

    done();
  })
);

/**
 * Move content files
 */
gulp.task(
  "move-content-files",
  gulp.series(function () {
    return gulp
      .src(paths.src.root + "/**/*.{php,html}", { allowEmpty: true })
      .pipe(plumber())
      .pipe(cache("move-content-files"))
      .on("error", notify.onError())
      .pipe(gulp.dest(paths.public.root));
    done();
  })
);

/**
 * Move assets
 */
gulp.task(
  "move-assets",
  gulp.series(function (done) {
    return gulp
      .src(paths.src.assets + "**/*", { allowEmpty: true })
      .pipe(plumber())
      .pipe(cache("move-assets"))
      .on("error", notify.onError())
      .pipe(gulp.dest(paths.public.assets));
    done();
  })
);

/**
 * Optimizing images
 */
gulp.task(
  "optim-images",
  gulp.series(function (done) {
    return gulp
      .src(paths.public.imgs + "**/*", { allowEmpty: true })
      .pipe(imagemin())
      .pipe(gulp.dest(paths.public.imgs));
    done();
  })
);

/**
 * Move screenshot
 */
gulp.task(
  "move-screenshot",
  gulp.series(function (done) {
    return gulp
      .src(paths.src.screenshot, { allowEmpty: true })
      .pipe(plumber())
      .pipe(cache("move-screenshot"))
      .on("error", notify.onError())
      .pipe(gulp.dest(paths.public.screenshot));
    done();
  })
);



/**
 * Open BrowserSync Window
 */
gulp.task(
  "browser-sync",
  gulp.series(function (done) {
    browserSync.init({
      proxy: base.browserSyncProxy,
    });
    done();
  })
);

/**
 * Reload Browser Sync
 */
gulp.task(
  "browser-sync-reload",
  gulp.series(function (done) {
    browserSync.reload();
    done();
  })
);

/**
 * Watch
 */
gulp.task(
  "default",
  gulp.series(
    "clean",

    gulp.parallel(
      "scripts.lib.public",
      "scripts.main.public",
      "styles.public",
      "move-content-files",
      "move-assets",
      "move-screenshot",
      "optim-images",

    ),
    "clean.dev.styles"
  )
);
gulp.task(
  "watch",
  gulp.parallel(
    [
      "scripts.lib.dev",
      "scripts.main.dev",
      "styles.dev",
      "styles-acfe.dev",
      "move-content-files",
      "move-assets",
      "move-screenshot",
      "browser-sync",
    ],
    function () {
      gulp.watch(
        paths.src.root + "/**/*.{php,html}",
        gulp.series("move-content-files")
      );
      gulp.watch(paths.src.assets + "**/*", gulp.series("move-assets"));
      gulp.watch(paths.src.js.site + "*.js", gulp.series("scripts.main.dev"));
      gulp.watch(paths.src.js.lib + "*.js", gulp.series("scripts.lib.dev"));
      gulp.watch(
        [
          paths.src.root + "/**/*.{php,svg,js}",
          paths.src.css + "**/*.scss",
          "!" + paths.src.css + "acfe/acfe-style.scss",
        ],
        gulp.series("styles.dev")
      );
      gulp.watch(
        paths.css + "acfe/acfe-style.scss",
        gulp.series("styles-acfe.dev")
      );
      gulp.watch(
        paths.public.root + "/**/*",
        gulp.series("browser-sync-reload")
      );
    }
  )
);



