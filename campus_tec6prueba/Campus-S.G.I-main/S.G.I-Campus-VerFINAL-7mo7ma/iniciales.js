function GetIniciales(name) {
    return name
        .split(' ')                  // separar por espacios ej: Tobias Corrales ["Tobias", "Corrales"]
        .map(word => word[0])        // primera letra de cada palabra
        .join('')                     // juntarlas
        .toUpperCase();               
}
function setProfilePlaceholder(imgEl, name) {
    const initials = GetIniciales(name);
    imgEl.setAttribute('data-src', `holder.js/200x200?bg=3a5a97&fg=ffffff&text=${initials}&size=25`);
    Holder.run({ images: imgEl });
}

// COMO USAR = setProfilePlaceholder(document.getElementById('profileImg'), "HERNANDEZ PEREZ"); 
//aca el nombre lo sacariamos por medio de la bd usadon php