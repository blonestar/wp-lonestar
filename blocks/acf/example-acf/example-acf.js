document.addEventListener("DOMContentLoaded", () => {
    const blocks = document.querySelectorAll(".wp-block-lonestar-example-acf");
    blocks.forEach((block) => {
        block.setAttribute("data-enhanced", "true");
    });
});
