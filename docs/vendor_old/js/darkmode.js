//Dark & Light Mode button
let darkmode = localStorage.getItem('darkmode');
const themeSwitch = document.getElementById('theme-switch');
const images = document.querySelectorAll('.theme-image');

const lightModeImages = [
    'vendor/img/popup-light.png',
    'vendor/img/dropdown-light.png',
    'vendor/img/formfield-light.png',
    'vendor/img/form-light.png',
    'vendor/img/upload-light.png',
    'vendor/img/database-light.png',
    'vendor/img/utls-light.png',
];

const darkModeImages = [
    'vendor/img/popup.png',
    'vendor/img/dropdown.png',
    'vendor/img/formfield.png',
    'vendor/img/form.png',
    'vendor/img/upload.png',
    'vendor/img/database.png',
    'vendor/img/utls.png'
];

const enableDarkmode = () => {
    document.body.classList.add('darkmode');
    localStorage.setItem('darkmode', 'active');
    updateImages(darkModeImages);
};

const disableDarkmode = () => {
    document.body.classList.remove('darkmode');
    localStorage.setItem('darkmode', null);
    updateImages(lightModeImages);
};

const updateImages = (imageSources) => {
    images.forEach((img, index) => {
        img.src = imageSources[index];
    });
};

if(darkmode === 'active') enableDarkmode();

themeSwitch.addEventListener('click', () => {
    darkmode = localStorage.getItem('darkmode');
    darkmode !== 'active' ? enableDarkmode() : disableDarkmode();
});